<?php

namespace Aghfatehi\Zatca\Services;

use Aghfatehi\Zatca\DTO\ComplianceResultDTO;
use Aghfatehi\Zatca\DTO\InvoiceDTO;
use Aghfatehi\Zatca\Enums\Environment;
use Aghfatehi\Zatca\Exceptions\CertificateException;
use Aghfatehi\Zatca\Exceptions\ComplianceException;
use Aghfatehi\Zatca\Logging\ZatcaLogger;

class Phase2Service
{
    public function __construct(
        private ApiClient          $apiClient,
        private CertificateService  $certificateService,
        private InvoiceSignerService $invoiceSigner,
        private ZatcaLogger        $logger,
    ) {}

    public function generateKeysAndCsr(array $egsUnit, string $solutionName = 'ERP'): array
    {
        // @todo: accept EgsUnitDTO instead of raw array; create EgsUnitDTO at call site
        $this->logger->info('Phase2: Generating keys and CSR');

        $privateKey = $this->certificateService->generateEcKeyPair();

        $csr = $this->certificateService->generateCsr(
            privateKey: $privateKey,
            solutionName: $solutionName,
            egsSerialNumber: $egsUnit['uuid'] ?? '',
            vatNumber: $egsUnit['vat_number'] ?? '',
            branchLocation: ($egsUnit['location']['building'] ?? '') . ' ' . ($egsUnit['location']['street'] ?? ''),
            branchIndustry: $egsUnit['branch_industry'] ?? '',
            branchName: $egsUnit['branch_name'] ?? '',
            taxpayerName: $egsUnit['vat_name'] ?? '',
            taxpayerProvidedId: $egsUnit['custom_id'] ?? $egsUnit['uuid'] ?? '',
            production: app(Environment::class)->isProduction(),
        );

        return [
            'private_key' => $privateKey,
            'csr' => $csr,
        ];
    }

    public function issueComplianceCertificate(string $csr, string $otp): ComplianceResultDTO
    {
        $this->logger->info('Phase2: Issuing compliance certificate');

        try {
            $response = $this->apiClient->post('/compliance', [
                'csr' => base64_encode($csr),
            ], otp: $otp);

            $issuedCert = base64_decode($response->binarySecurityToken);
            $response->binarySecurityToken = "-----BEGIN CERTIFICATE-----\n{$issuedCert}\n-----END CERTIFICATE-----";

            return ComplianceResultDTO::fromApiResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Phase2: Compliance certificate issuance failed', [
                'error' => $e->getMessage(),
            ]);
            throw new CertificateException('Failed to issue compliance certificate: ' . $e->getMessage(), 0, $e);
        }
    }

    public function signInvoice(InvoiceDTO $invoice, array $egsUnit, string $certificate, string $privateKey): array
    {
        $this->logger->info('Phase2: Signing invoice', [
            'serial' => $invoice->invoiceSerialNumber,
        ]);

        return $this->invoiceSigner->sign($invoice, $egsUnit, $certificate, $privateKey);
    }

    public function checkCompliance(string $signedInvoiceXml, string $invoiceHash, string $certificate, string $secret): ComplianceResultDTO
    {
        $this->logger->info('Phase2: Checking invoice compliance');

        try {
            $response = $this->apiClient->post('/compliance/invoices', [
                'invoiceHash' => $invoiceHash,
                'uuid' => $this->extractUuid($signedInvoiceXml),
                'invoice' => base64_encode($signedInvoiceXml),
            ], certificate: $certificate, secret: $secret);

            return ComplianceResultDTO::fromApiResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Phase2: Compliance check failed', [
                'error' => $e->getMessage(),
            ]);
            throw new ComplianceException('Invoice compliance check failed: ' . $e->getMessage());
        }
    }

    public function clearInvoice(string $signedInvoiceXml, string $invoiceHash, string $certificate, string $secret): ComplianceResultDTO
    {
        $this->logger->info('Phase2: Clearing invoice');

        try {
            $response = $this->apiClient->post('/invoices/clearance', [
                'invoiceHash' => $invoiceHash,
                'uuid' => $this->extractUuid($signedInvoiceXml),
                'invoice' => base64_encode($signedInvoiceXml),
            ], certificate: $certificate, secret: $secret);

            return ComplianceResultDTO::fromApiResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Phase2: Invoice clearance failed', [
                'error' => $e->getMessage(),
            ]);
            throw new ComplianceException('Invoice clearance failed: ' . $e->getMessage());
        }
    }

    public function reportInvoice(string $signedInvoiceXml, string $invoiceHash, string $certificate, string $secret): ComplianceResultDTO
    {
        $this->logger->info('Phase2: Reporting invoice');

        try {
            $response = $this->apiClient->post('/invoices/reporting', [
                'invoiceHash' => $invoiceHash,
                'uuid' => $this->extractUuid($signedInvoiceXml),
                'invoice' => base64_encode($signedInvoiceXml),
            ], certificate: $certificate, secret: $secret);

            return ComplianceResultDTO::fromApiResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Phase2: Invoice reporting failed', [
                'error' => $e->getMessage(),
            ]);
            throw new ComplianceException('Invoice reporting failed: ' . $e->getMessage());
        }
    }

    public function submitInvoice(string $signedInvoiceXml, string $invoiceHash, string $certificate, string $secret): ComplianceResultDTO
    {
        $environment = app(Environment::class);

        if ($environment->isSandbox()) {
            return $this->checkCompliance($signedInvoiceXml, $invoiceHash, $certificate, $secret);
        }

        $clearanceResult = $this->clearInvoice($signedInvoiceXml, $invoiceHash, $certificate, $secret);

        if (!$clearanceResult->success) {
            return $this->reportInvoice($signedInvoiceXml, $invoiceHash, $certificate, $secret);
        }

        return $clearanceResult;
    }

    private function extractUuid(string $xml): string
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        $uuids = $doc->getElementsByTagName('UUID');

        return $uuids->length > 0 ? $uuids->item(0)->textContent : '';
    }
}
