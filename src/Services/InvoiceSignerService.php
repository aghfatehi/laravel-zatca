<?php

namespace Aghfatehi\Zatca\Services;

use Aghfatehi\Zatca\Contracts\InvoiceSignerInterface;
use Aghfatehi\Zatca\DTO\InvoiceDTO;
use Aghfatehi\Zatca\Enums\InvoiceType;
use Aghfatehi\Zatca\Logging\ZatcaLogger;
use DOMDocument;

class InvoiceSignerService implements InvoiceSignerInterface
{
    private QrCodeService $qrService;
    private CertificateService $certificateService;
    private ZatcaLogger $logger;

    public function __construct(
        QrCodeService $qrService,
        CertificateService $certificateService,
        ZatcaLogger $logger,
    ) {
        $this->qrService = $qrService;
        $this->certificateService = $certificateService;
        $this->logger = $logger;
    }

    public function sign(InvoiceDTO $invoice, array $egsUnit, string $certificate, string $privateKey): array
    {
        $this->logger->info('Starting invoice signing process', [
            'serial' => $invoice->invoiceSerialNumber,
        ]);

        $invoiceXml = $this->buildXml($invoice, $egsUnit);
        $invoiceHash = $this->computeInvoiceHash($invoiceXml);
        $certInfo = $this->certificateService->parseCertificateInfo($certificate);
        $digitalSignature = $this->createDigitalSignature($invoiceHash, $privateKey);

        $qrData = $this->qrService->generatePhase2Qr(
            sellerName: $egsUnit['vat_name'] ?? '',
            vatNumber: $egsUnit['vat_number'] ?? '',
            invoiceDate: date('Y-m-d\TH:i:s\Z', strtotime($invoice->issueDate . ' ' . $invoice->issueTime)),
            totalAmount: $this->getTotalAmount($invoiceXml),
            taxAmount: $this->getTaxAmount($invoiceXml),
            invoiceHash: $invoiceHash,
            digitalSignature: $digitalSignature,
            publicKey: $certInfo['public_key'],
            certificateSignature: $certInfo['signature'],
        );

        $signedInvoiceXml = $this->embedSignatures(
            invoiceXml: $invoiceXml,
            invoiceHash: $invoiceHash,
            digitalSignature: $digitalSignature,
            certificate: $certificate,
            certInfo: $certInfo,
            qrData: $qrData,
        );

        $this->logger->info('Invoice signed successfully', [
            'serial' => $invoice->invoiceSerialNumber,
            'hash' => $invoiceHash,
        ]);

        return [
            'signed_xml' => $signedInvoiceXml,
            'invoice_hash' => $invoiceHash,
            'qr_tlv' => $qrData,
            'public_key' => $certInfo['public_key'],
        ];
    }

    public function buildXml(InvoiceDTO $invoice, array $egsUnit): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;
        $doc->preserveWhiteSpace = false;

        $ublNs = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
        $cac = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
        $cbc = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $ext = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

        $invoiceEl = $doc->createElementNS($ublNs, 'Invoice');
        $invoiceEl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', $cac);
        $invoiceEl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', $cbc);
        $invoiceEl->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', $ext);

        $doc->appendChild($invoiceEl);

        $this->appendElement($doc, $invoiceEl, 'cbc:ProfileID', 'reporting:1.0');
        $this->appendElement($doc, $invoiceEl, 'cbc:ID', $invoice->invoiceSerialNumber);
        $this->appendElement($doc, $invoiceEl, 'cbc:UUID', $egsUnit['uuid'] ?? '');
        $this->appendElement($doc, $invoiceEl, 'cbc:IssueDate', $invoice->issueDate);
        $this->appendElement($doc, $invoiceEl, 'cbc:IssueTime', $invoice->issueTime);

        $invType = InvoiceType::tryFrom($invoice->invoiceType) ?? InvoiceType::Invoice;
        $typeCode = $doc->createElement('cbc:InvoiceTypeCode', (string)$invType->ublCode());
        $typeCode->setAttribute('name', $invType->ublName());
        $invoiceEl->appendChild($typeCode);

        $this->appendElement($doc, $invoiceEl, 'cbc:DocumentCurrencyCode', $invoice->currency);
        $this->appendElement($doc, $invoiceEl, 'cbc:TaxCurrencyCode', $invoice->currency);

        $this->appendAdditionalDocRef($doc, $invoiceEl, 'ICV', (string)$invoice->invoiceCounterNumber);

        if ($invoice->previousInvoiceHash) {
            $pih = $this->appendAdditionalDocRef($doc, $invoiceEl, 'PIH');
            $attachment = $doc->createElement('cac:Attachment');
            $binaryObj = $doc->createElement('cbc:EmbeddedDocumentBinaryObject', $invoice->previousInvoiceHash);
            $binaryObj->setAttribute('mimeCode', 'text/plain');
            $attachment->appendChild($binaryObj);
            $pih->appendChild($attachment);
        }

        $qrRef = $this->appendAdditionalDocRef($doc, $invoiceEl, 'QR');

        $signature = $doc->createElement('cac:Signature');
        $this->appendElement($doc, $signature, 'cbc:ID', 'urn:oasis:names:specification:ubl:signature:Invoice');
        $this->appendElement($doc, $signature, 'cbc:SignatureMethod', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades');
        $invoiceEl->appendChild($signature);

        $this->appendSupplierParty($doc, $invoiceEl, $egsUnit);
        $this->appendCustomerParty($doc, $invoiceEl, $invoice, $egsUnit);

        $delivery = $doc->createElement('cac:Delivery');
        $this->appendElement($doc, $delivery, 'cbc:ActualDeliveryDate', $invoice->issueDate);
        $invoiceEl->appendChild($delivery);

        $payment = $doc->createElement('cac:PaymentMeans');
        $this->appendElement($doc, $payment, 'cbc:PaymentMeansCode', '10');
        $invoiceEl->appendChild($payment);

        $this->appendLineItems($doc, $invoiceEl, $invoice);
        $this->appendTaxTotals($doc, $invoiceEl, $invoice);
        $this->appendLegalMonetaryTotal($doc, $invoiceEl, $invoice);

        return $doc;
    }

    public function computeInvoiceHash(DOMDocument $invoiceXml): string
    {
        $pure = $this->getPureInvoiceString($invoiceXml);
        $pure = str_replace('<?xml version="1.0" encoding="UTF-8"?>' . "\n", '', $pure);
        $pure = str_replace('<cac:AccountingCustomerParty/>', '<cac:AccountingCustomerParty></cac:AccountingCustomerParty>', $pure);

        $hash = hash('sha256', trim($pure), true);

        return base64_encode($hash);
    }

    private function getPureInvoiceString(DOMDocument $doc): string
    {
        $clone = new DOMDocument();
        $clone->loadXML($doc->saveXML());

        $this->removeElementsByTagName($clone, 'UBLExtensions');
        $this->removeElementsByTagName($clone, 'Signature');
        $this->removeQrReference($clone);

        return $clone->saveXML();
    }

    private function removeElementsByTagName(DOMDocument $doc, string $tagName): void
    {
        while ($element = $doc->getElementsByTagName($tagName)->item(0)) {
            $element->parentNode->removeChild($element);
        }
    }

    private function removeQrReference(DOMDocument $doc): void
    {
        $refs = $doc->getElementsByTagName('AdditionalDocumentReference');
        for ($i = $refs->length - 1; $i >= 0; $i--) {
            $ref = $refs->item($i);
            $ids = $ref->getElementsByTagName('ID');
            if ($ids->length > 0 && $ids->item(0)->textContent === 'QR') {
                $ref->parentNode->removeChild($ref);
            }
        }
    }

    public function createDigitalSignature(string $invoiceHash, string $privateKey): string
    {
        $cleanKey = $this->certificateService->cleanPrivateKey($privateKey);
        $wrappedKey = "-----BEGIN EC PRIVATE KEY-----\n{$cleanKey}\n-----END EC PRIVATE KEY-----";

        openssl_sign(base64_encode($invoiceHash), $binarySignature, $wrappedKey, 'sha256');

        return base64_encode($binarySignature);
    }

    private function embedSignatures(
        DOMDocument $invoiceXml,
        string $invoiceHash,
        string $digitalSignature,
        string $certificate,
        array $certInfo,
        string $qrData,
    ): string {
        $xml = $invoiceXml->saveXML();

        $signTimestamp = date('Y-m-d\TH:i:s\Z');

        $signedPropsForSigning = $this->buildSignedPropertiesForSigning($signTimestamp, $certInfo);
        $signedPropsHash = base64_encode(openssl_digest($signedPropsForSigning, 'sha256'));

        $signedPropsXml = $this->buildSignedProperties($signTimestamp, $certInfo);

        $ublExtensions = $this->buildUblExtensions(
            invoiceHash: $invoiceHash,
            signedPropsHash: $signedPropsHash,
            digitalSignature: $digitalSignature,
            certificate: $certificate,
            signedPropsXml: $signedPropsXml,
        );

        $xml = str_replace('<!-- UBLExtensions -->', $ublExtensions, $xml);
        $xml = str_replace('<cac:AdditionalDocumentReference><cbc:ID>QR</cbc:ID></cac:AdditionalDocumentReference>', '<cac:AdditionalDocumentReference><cbc:ID>QR</cbc:ID><cac:Attachment><cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">' . $qrData . '</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference>', $xml);

        $signedDoc = new DOMDocument();
        $signedDoc->loadXML($xml);

        return $signedDoc->saveXML();
    }

    private function buildSignedPropertiesForSigning(string $timestamp, array $certInfo): string
    {
        return '<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">
            <xades:SignedSignatureProperties>
                <xades:SigningTime>' . $timestamp . '</xades:SigningTime>
                <xades:SigningCertificate>
                    <xades:Cert>
                        <xades:CertDigest>
                            <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                            <ds:DigestValue>' . $certInfo['hash'] . '</ds:DigestValue>
                        </xades:CertDigest>
                        <xades:IssuerSerial>
                            <ds:X509IssuerName>' . $certInfo['issuer'] . '</ds:X509IssuerName>
                            <ds:X509SerialNumber>' . $certInfo['serial_number'] . '</ds:X509SerialNumber>
                        </xades:IssuerSerial>
                    </xades:Cert>
                </xades:SigningCertificate>
            </xades:SignedSignatureProperties>
        </xades:SignedProperties>';
    }

    private function buildSignedProperties(string $timestamp, array $certInfo): string
    {
        return '<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">
            <xades:SignedSignatureProperties>
                <xades:SigningTime>' . $timestamp . '</xades:SigningTime>
                <xades:SigningCertificate>
                    <xades:Cert>
                        <xades:CertDigest>
                            <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                            <ds:DigestValue>' . $certInfo['hash'] . '</ds:DigestValue>
                        </xades:CertDigest>
                        <xades:IssuerSerial>
                            <ds:X509IssuerName>' . $certInfo['issuer'] . '</ds:X509IssuerName>
                            <ds:X509SerialNumber>' . $certInfo['serial_number'] . '</ds:X509SerialNumber>
                        </xades:IssuerSerial>
                    </xades:Cert>
                </xades:SigningCertificate>
            </xades:SignedSignatureProperties>
        </xades:SignedProperties>';
    }

    private function buildUblExtensions(
        string $invoiceHash,
        string $signedPropsHash,
        string $digitalSignature,
        string $certificate,
        string $signedPropsXml,
    ): string {
        $cleanCert = $this->certificateService->cleanCertificate($certificate);

        return '<ext:UBLExtensions>
            <ext:UBLExtension>
                <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>
                <ext:ExtensionContent>
                    <sig:UBLDocumentSignatures xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2" xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2" xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2">
                        <sac:SignatureInformation>
                            <cbc:ID>urn:oasis:names:specification:ubl:signature:1</cbc:ID>
                            <sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>
                            <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="signature">
                                <ds:SignedInfo>
                                    <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                                    <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>
                                    <ds:Reference Id="invoiceSignedData" URI="">
                                        <ds:Transforms>
                                            <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116"><ds:XPath>not(//ancestor-or-self::ext:UBLExtensions)</ds:XPath></ds:Transform>
                                            <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116"><ds:XPath>not(//ancestor-or-self::cac:Signature)</ds:XPath></ds:Transform>
                                            <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116"><ds:XPath>not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID=\'QR\'])</ds:XPath></ds:Transform>
                                            <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                                        </ds:Transforms>
                                        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                        <ds:DigestValue>' . $invoiceHash . '</ds:DigestValue>
                                    </ds:Reference>
                                    <ds:Reference Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties" URI="#xadesSignedProperties">
                                        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                        <ds:DigestValue>' . $signedPropsHash . '</ds:DigestValue>
                                    </ds:Reference>
                                </ds:SignedInfo>
                                <ds:SignatureValue>' . $digitalSignature . '</ds:SignatureValue>
                                <ds:KeyInfo>
                                    <ds:X509Data>
                                        <ds:X509Certificate>' . $cleanCert . '</ds:X509Certificate>
                                    </ds:X509Data>
                                </ds:KeyInfo>
                                <ds:Object>
                                    <xades:QualifyingProperties Target="signature" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#">
                                        ' . $signedPropsXml . '
                                    </xades:QualifyingProperties>
                                </ds:Object>
                            </ds:Signature>
                        </sac:SignatureInformation>
                    </sig:UBLDocumentSignatures>
                </ext:ExtensionContent>
            </ext:UBLExtension>
        </ext:UBLExtensions>';
    }

    private function appendSupplierParty(DOMDocument $doc, \DOMElement $root, array $egsUnit): void
    {
        $supplier = $doc->createElement('cac:AccountingSupplierParty');
        $party = $doc->createElement('cac:Party');

        $partyId = $doc->createElement('cac:PartyIdentification');
        $this->appendElement($doc, $partyId, 'cbc:ID', $egsUnit['crn_number'] ?? '');
        $partyId->firstChild->setAttribute('schemeID', 'CRN');
        $party->appendChild($partyId);

        $address = $doc->createElement('cac:PostalAddress');
        $loc = $egsUnit['location'] ?? [];
        $this->appendElement($doc, $address, 'cbc:StreetName', $loc['street'] ?? '');
        $this->appendElement($doc, $address, 'cbc:BuildingNumber', $loc['building'] ?? '0000');
        $this->appendElement($doc, $address, 'cbc:PlotIdentification', $loc['plot_identification'] ?? '0000');
        $this->appendElement($doc, $address, 'cbc:CitySubdivisionName', $loc['city_subdivision'] ?? '');
        $this->appendElement($doc, $address, 'cbc:CityName', $loc['city'] ?? '');
        $this->appendElement($doc, $address, 'cbc:PostalZone', $loc['postal_zone'] ?? '');
        $country = $doc->createElement('cac:Country');
        $this->appendElement($doc, $country, 'cbc:IdentificationCode', 'SA');
        $address->appendChild($country);
        $party->appendChild($address);

        $taxScheme = $doc->createElement('cac:PartyTaxScheme');
        $this->appendElement($doc, $taxScheme, 'cbc:CompanyID', $egsUnit['vat_number'] ?? '');
        $ts = $doc->createElement('cac:TaxScheme');
        $this->appendElement($doc, $ts, 'cbc:ID', 'VAT');
        $taxScheme->appendChild($ts);
        $party->appendChild($taxScheme);

        $legal = $doc->createElement('cac:PartyLegalEntity');
        $this->appendElement($doc, $legal, 'cbc:RegistrationName', $egsUnit['vat_name'] ?? '');
        $party->appendChild($legal);

        $supplier->appendChild($party);
        $root->appendChild($supplier);
    }

    private function appendCustomerParty(DOMDocument $doc, \DOMElement $root, InvoiceDTO $invoice, array $egsUnit): void
    {
        $customer = $doc->createElement('cac:AccountingCustomerParty');
        $party = $doc->createElement('cac:Party');

        if ($invoice->customerVatNumber) {
            $partyId = $doc->createElement('cac:PartyIdentification');
            $this->appendElement($doc, $partyId, 'cbc:ID', $invoice->customerVatNumber);
            $partyId->firstChild->setAttribute('schemeID', 'NAT');
            $party->appendChild($partyId);
        }

        $address = $doc->createElement('cac:PostalAddress');
        $loc = $egsUnit['location'] ?? [];
        $this->appendElement($doc, $address, 'cbc:StreetName', $loc['street'] ?? '');
        $this->appendElement($doc, $address, 'cbc:BuildingNumber', $loc['building'] ?? '0000');
        $this->appendElement($doc, $address, 'cbc:CitySubdivisionName', $loc['city_subdivision'] ?? '');
        $this->appendElement($doc, $address, 'cbc:CityName', $loc['city'] ?? '');
        $this->appendElement($doc, $address, 'cbc:PostalZone', $loc['postal_zone'] ?? '');
        $country = $doc->createElement('cac:Country');
        $this->appendElement($doc, $country, 'cbc:IdentificationCode', 'SA');
        $address->appendChild($country);
        $party->appendChild($address);

        if ($invoice->customerName) {
            $legal = $doc->createElement('cac:PartyLegalEntity');
            $this->appendElement($doc, $legal, 'cbc:RegistrationName', $invoice->customerName);
            $party->appendChild($legal);
        }

        $customer->appendChild($party);
        $root->appendChild($customer);
    }

    private function appendLineItems(DOMDocument $doc, \DOMElement $root, InvoiceDTO $invoice): void
    {
        foreach ($invoice->lineItems as $item) {
            $line = $doc->createElement('cac:InvoiceLine');
            $this->appendElement($doc, $line, 'cbc:ID', (string)($item['id'] ?? ''));

            $qty = $doc->createElement('cbc:InvoicedQuantity', (string)($item['quantity'] ?? 0));
            $qty->setAttribute('unitCode', 'PCE');
            $line->appendChild($qty);

            $subtotal = ($item['tax_exclusive_price'] ?? 0) * ($item['quantity'] ?? 0);
            $discountsTotal = array_sum(array_column($item['discounts'] ?? [], 'amount'));
            $taxableAmount = $subtotal - $discountsTotal;
            $vatAmount = $taxableAmount * ($item['VAT_percent'] ?? 0);

            $this->appendElement($doc, $line, 'cbc:LineExtensionAmount', number_format($taxableAmount, 2, '.', ''), ['currencyID' => 'SAR']);

            $taxTotal = $doc->createElement('cac:TaxTotal');
            $this->appendElement($doc, $taxTotal, 'cbc:TaxAmount', number_format($vatAmount, 2, '.', ''), ['currencyID' => 'SAR']);
            $this->appendElement($doc, $taxTotal, 'cbc:RoundingAmount', number_format($taxableAmount + $vatAmount, 2, '.', ''), ['currencyID' => 'SAR']);
            $line->appendChild($taxTotal);

            $itemEl = $doc->createElement('cac:Item');
            $this->appendElement($doc, $itemEl, 'cbc:Name', $item['name'] ?? '');

            $cat = $doc->createElement('cac:ClassifiedTaxCategory');
            $vatPercent = ($item['VAT_percent'] ?? 0) * 100;
            $this->appendElement($doc, $cat, 'cbc:ID', $vatPercent > 0 ? 'S' : 'O');
            $this->appendElement($doc, $cat, 'cbc:Percent', number_format($vatPercent, 2, '.', ''));
            $ts2 = $doc->createElement('cac:TaxScheme');
            $this->appendElement($doc, $ts2, 'cbc:ID', 'VAT');
            $cat->appendChild($ts2);
            $itemEl->appendChild($cat);
            $line->appendChild($itemEl);

            $price = $doc->createElement('cac:Price');
            $this->appendElement($doc, $price, 'cbc:PriceAmount', (string)($item['tax_exclusive_price'] ?? 0), ['currencyID' => 'SAR']);
            $line->appendChild($price);

            $root->appendChild($line);
        }
    }

    private function appendTaxTotals(DOMDocument $doc, \DOMElement $root, InvoiceDTO $invoice): void
    {
        $totalVat = 0;
        $taxSubtotals = [];

        foreach ($invoice->lineItems as $item) {
            $subtotal = ($item['tax_exclusive_price'] ?? 0) * ($item['quantity'] ?? 0);
            $discounts = array_sum(array_column($item['discounts'] ?? [], 'amount'));
            $taxable = $subtotal - $discounts;
            $vat = $taxable * ($item['VAT_percent'] ?? 0);
            $totalVat += $vat;

            $percent = ($item['VAT_percent'] ?? 0) * 100;
            $key = (string)$percent;
            if (!isset($taxSubtotals[$key])) {
                $taxSubtotals[$key] = ['taxable' => 0, 'vat' => 0, 'percent' => $item['VAT_percent'] ?? 0];
            }
            $taxSubtotals[$key]['taxable'] += $taxable;
            $taxSubtotals[$key]['vat'] += $vat;
        }

        $taxTotal1 = $doc->createElement('cac:TaxTotal');
        $this->appendElement($doc, $taxTotal1, 'cbc:TaxAmount', number_format($totalVat, 2, '.', ''), ['currencyID' => 'SAR']);

        foreach ($taxSubtotals as $sub) {
            $subTotalEl = $doc->createElement('cac:TaxSubtotal');
            $this->appendElement($doc, $subTotalEl, 'cbc:TaxableAmount', number_format($sub['taxable'], 2, '.', ''), ['currencyID' => 'SAR']);
            $this->appendElement($doc, $subTotalEl, 'cbc:TaxAmount', number_format($sub['vat'], 2, '.', ''), ['currencyID' => 'SAR']);

            $cat = $doc->createElement('cac:TaxCategory');
            $id = $doc->createElement('cbc:ID', $sub['percent'] > 0 ? 'S' : 'O');
            $id->setAttribute('schemeID', 'UN/ECE 5305');
            $id->setAttribute('schemeAgencyID', '6');
            $cat->appendChild($id);
            $this->appendElement($doc, $cat, 'cbc:Percent', number_format($sub['percent'] * 100, 2, '.', ''));
            $ts = $doc->createElement('cac:TaxScheme');
            $tsId = $doc->createElement('cbc:ID', 'VAT');
            $tsId->setAttribute('schemeID', 'UN/ECE 5153');
            $tsId->setAttribute('schemeAgencyID', '6');
            $ts->appendChild($tsId);
            $cat->appendChild($ts);
            $subTotalEl->appendChild($cat);
            $taxTotal1->appendChild($subTotalEl);
        }

        $root->appendChild($taxTotal1);

        $taxTotal2 = $doc->createElement('cac:TaxTotal');
        $this->appendElement($doc, $taxTotal2, 'cbc:TaxAmount', number_format($totalVat, 2, '.', ''), ['currencyID' => 'SAR']);
        $root->appendChild($taxTotal2);
    }

    private function appendLegalMonetaryTotal(DOMDocument $doc, \DOMElement $root, InvoiceDTO $invoice): void
    {
        $totalSubtotal = 0;
        $totalVat = 0;

        foreach ($invoice->lineItems as $item) {
            $subtotal = ($item['tax_exclusive_price'] ?? 0) * ($item['quantity'] ?? 0);
            $discounts = array_sum(array_column($item['discounts'] ?? [], 'amount'));
            $taxable = $subtotal - $discounts;
            $totalSubtotal += $taxable;
            $totalVat += $taxable * ($item['VAT_percent'] ?? 0);
        }

        $total = $doc->createElement('cac:LegalMonetaryTotal');
        $this->appendElement($doc, $total, 'cbc:LineExtensionAmount', number_format($totalSubtotal, 2, '.', ''), ['currencyID' => 'SAR']);
        $this->appendElement($doc, $total, 'cbc:TaxExclusiveAmount', number_format($totalSubtotal, 2, '.', ''), ['currencyID' => 'SAR']);
        $this->appendElement($doc, $total, 'cbc:TaxInclusiveAmount', number_format($totalSubtotal + $totalVat, 2, '.', ''), ['currencyID' => 'SAR']);
        $this->appendElement($doc, $total, 'cbc:AllowanceTotalAmount', '0', ['currencyID' => 'SAR']);
        $this->appendElement($doc, $total, 'cbc:PrepaidAmount', '0', ['currencyID' => 'SAR']);
        $this->appendElement($doc, $total, 'cbc:PayableAmount', number_format($totalSubtotal + $totalVat, 2, '.', ''), ['currencyID' => 'SAR']);
        $root->appendChild($total);
    }

    private function appendAdditionalDocRef(DOMDocument $doc, \DOMElement $root, string $id, ?string $uuid = null): \DOMElement
    {
        $ref = $doc->createElement('cac:AdditionalDocumentReference');
        $this->appendElement($doc, $ref, 'cbc:ID', $id);

        if ($uuid !== null) {
            $this->appendElement($doc, $ref, 'cbc:UUID', $uuid);
        }

        $root->appendChild($ref);
        return $ref;
    }

    private function appendElement(DOMDocument $doc, \DOMElement $parent, string $name, string $value = '', array $attributes = []): void
    {
        $element = $doc->createElement($name, $value);

        foreach ($attributes as $attrName => $attrValue) {
            $element->setAttribute($attrName, $attrValue);
        }

        $parent->appendChild($element);
    }

    private function getTotalAmount(DOMDocument $xml): string
    {
        $amounts = $xml->getElementsByTagName('TaxInclusiveAmount');
        return $amounts->length > 0 ? $amounts->item(0)->textContent : '0';
    }

    private function getTaxAmount(DOMDocument $xml): string
    {
        $taxTotals = $xml->getElementsByTagName('TaxTotal');
        if ($taxTotals->length > 0) {
            $amounts = $taxTotals->item(0)->getElementsByTagName('TaxAmount');
            if ($amounts->length > 0) {
                return $amounts->item(0)->textContent;
            }
        }
        return '0';
    }
}
