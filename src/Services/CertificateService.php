<?php

namespace Aghfatehi\Zatca\Services;

use Aghfatehi\Zatca\Exceptions\CertificateException;
use Aghfatehi\Zatca\Logging\ZatcaLogger;

class CertificateService
{
    public function __construct(
        private ZatcaLogger $logger,
    ) {}

    public function generateEcKeyPair(): string
    {
        $this->logger->info('Generating secp256k1 EC key pair');

        $result = shell_exec('openssl ecparam -name secp256k1 -genkey 2>&1');

        if ($result === null || $result === '') {
            throw new CertificateException('Failed to generate EC key pair. OpenSSL may not be available.');
        }

        $result = trim($result);

        if (!str_contains($result, 'BEGIN EC PRIVATE KEY')) {
            throw new CertificateException('Invalid EC private key generated');
        }

        $this->logger->info('EC key pair generated successfully');

        return $result;
    }

    public function generateCsr(
        string $privateKey,
        string $solutionName,
        string $egsSerialNumber,
        string $vatNumber,
        string $branchLocation,
        string $branchIndustry,
        string $branchName,
        string $taxpayerName,
        string $taxpayerProvidedId,
        bool $production = false,
    ): string {
        $this->logger->info('Generating CSR', [
            'solution_name' => $solutionName,
            'production' => $production,
        ]);

        $tmpDir = $this->getTmpDir();
        $keyFile = $tmpDir . '/' . uniqid('zatca_key_', true) . '.pem';
        $cnfFile = $tmpDir . '/' . uniqid('zatca_cnf_', true) . '.cnf';

        try {
            file_put_contents($keyFile, $privateKey);

            $csrConfig = $this->buildCsrConfig(
                solutionName: $solutionName,
                egsSerialNumber: $egsSerialNumber,
                vatNumber: $vatNumber,
                branchLocation: $branchLocation,
                branchIndustry: $branchIndustry,
                branchName: $branchName,
                taxpayerName: $taxpayerName,
                taxpayerProvidedId: $taxpayerProvidedId,
                production: $production,
            );

            file_put_contents($cnfFile, $csrConfig);

            $result = shell_exec("openssl req -new -sha256 -key " . escapeshellarg($keyFile) . " -config " . escapeshellarg($cnfFile) . " 2>&1");

            if ($result === null || !str_contains($result, 'BEGIN CERTIFICATE REQUEST')) {
                throw new CertificateException('CSR generation failed: ' . ($result ?? 'openssl returned empty'));
            }
        } finally {
            @unlink($keyFile);
            @unlink($cnfFile);
        }

        $this->logger->info('CSR generated successfully');

        return trim($result);
    }

    public function parseCertificateInfo(string $certificate): array
    {
        $cleaned = $this->cleanCertificate($certificate);
        $wrapped = "-----BEGIN CERTIFICATE-----\n{$cleaned}\n-----END CERTIFICATE-----";

        $x509 = openssl_x509_parse($wrapped);

        if (!$x509) {
            throw new CertificateException('Failed to parse certificate');
        }

        $publicKeyResource = openssl_get_publickey($wrapped);

        if (!$publicKeyResource) {
            throw new CertificateException('Failed to extract public key from certificate');
        }

        $keyDetails = openssl_pkey_get_details($publicKeyResource);
        $publicKey = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'], '', $keyDetails['key'] ?? '');
        $publicKey = base64_decode(trim($publicKey));

        $hash = $this->getCertificateHash($cleaned);
        $issuer = 'CN=' . implode(', ', array_reverse((array)$x509['issuer']));

        $signature = $this->extractCertificateSignature($wrapped);

        return [
            'hash' => $hash,
            'issuer' => $issuer,
            'serial_number' => $x509['serialNumber'] ?? '',
            'public_key' => $publicKey,
            'signature' => $signature,
        ];
    }

    private function getCertificateHash(string $cleanCert): string
    {
        return base64_encode(openssl_digest($cleanCert, 'sha256'));
    }

    private function extractCertificateSignature(string $certPem): string
    {
        $res = openssl_x509_read($certPem);
        openssl_x509_export($res, $out, false);

        $parts = explode('-----BEGIN CERTIFICATE-----', $out);
        $lines = explode("\n", $parts[0]);

        $sigLine = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !str_contains($line, ':')) {
                continue;
            }
            $sigLine .= str_replace([':', ' '], '', $line);
        }

        $hex = preg_replace('/[^0-9a-fA-F]/', '', $sigLine);

        return bin2hex(pack('H*', $hex));
    }

    public function cleanCertificate(string $certificate): string
    {
        $cleaned = str_replace('-----BEGIN CERTIFICATE-----', '', $certificate);
        $cleaned = str_replace('-----END CERTIFICATE-----', '', $cleaned);
        return trim($cleaned);
    }

    public function cleanPrivateKey(string $privateKey): string
    {
        $cleaned = str_replace('-----BEGIN EC PRIVATE KEY-----', '', $privateKey);
        $cleaned = str_replace('-----END EC PRIVATE KEY-----', '', $cleaned);
        return trim($cleaned);
    }

    private function buildCsrConfig(
        string $solutionName,
        string $egsSerialNumber,
        string $vatNumber,
        string $branchLocation,
        string $branchIndustry,
        string $branchName,
        string $taxpayerName,
        string $taxpayerProvidedId,
        bool $production,
    ): string {
        $productionValue = $production ? 'ZATCA-Code-Signing' : 'TSTZATCA-Code-Signing';
        $egsSerial = "1-{$solutionName}|2-{$egsSerialNumber}";

        return <<<CNF
[req]
prompt = no
utf8 = no
distinguished_name = dn
req_extensions = ext

[ext]
1.3.6.1.4.1.311.20.2 = ASN1:UTF8String:{$productionValue}
subjectAltName = dirName:dir_sect

[dir_sect]
SN = {$egsSerial}
UID = {$vatNumber}
title = 0100
registeredAddress = {$branchLocation}
businessCategory = {$branchIndustry}

[dn]
commonName = {$taxpayerProvidedId}
organizationalUnitName = {$branchName}
organizationName = {$taxpayerName}
countryName = SA
CNF;
    }

    private function getTmpDir(): string
    {
        $tmpDir = storage_path('app/tmp/zatca');

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return $tmpDir;
    }
}
