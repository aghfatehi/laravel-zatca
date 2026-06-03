<?php

namespace Aghfatehi\Zatca\Contracts;

interface ZatcaClientInterface
{
    public function complianceCheck(string $signedInvoiceXml, string $invoiceHash, string $uuid): object;
    public function clearance(string $signedInvoiceXml, string $invoiceHash, string $uuid): object;
    public function reporting(string $signedInvoiceXml, string $invoiceHash, string $uuid): object;
    public function issueCertificate(string $csr, string $otp): object;
}
