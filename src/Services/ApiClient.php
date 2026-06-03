<?php

namespace Aghfatehi\Zatca\Services;

use Aghfatehi\Zatca\Enums\Environment;
use Aghfatehi\Zatca\Exceptions\ZatcaException;
use Aghfatehi\Zatca\Logging\ZatcaLogger;

class ApiClient
{
    private string $baseUrl;
    private string $version;
    private int $timeout;

    public function __construct(
        private Environment $environment,
        private ZatcaLogger $logger,
    ) {
        $config = config('zatca.api');
        $env = $environment->value;
        $this->baseUrl = $config[$env]['base'] ?? '';
        $this->version = $config[$env]['version'] ?? 'V2';
        $this->timeout = $config['timeout'] ?? 60;
    }

    public function post(string $endpoint, array $data, ?string $certificate = null, ?string $secret = null, ?string $otp = null): object
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = $this->buildHeaders($certificate, $secret, $otp);

        $this->logger->info('ZATCA API request', [
            'url' => $this->maskUrl($url),
            'endpoint' => $endpoint,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error('ZATCA API connection error', ['error' => $curlError]);
            throw new ZatcaException("ZATCA API connection failed: {$curlError}");
        }

        $decoded = json_decode($response);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('ZATCA API invalid JSON response', [
                'http_code' => $httpCode,
                'response_preview' => mb_substr($response, 0, 500),
            ]);
            throw new ZatcaException('Invalid JSON response from ZATCA API');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = $decoded->errors[0]->message ?? $decoded->error ?? 'Unknown error';
            $this->logger->error('ZATCA API error response', [
                'http_code' => $httpCode,
                'error' => $errorMsg,
            ]);
            throw new ZatcaException("ZATCA API error ({$httpCode}): {$errorMsg}", $httpCode);
        }

        $this->logger->info('ZATCA API success', [
            'http_code' => $httpCode,
            'endpoint' => $endpoint,
        ]);

        return $decoded;
    }

    private function buildHeaders(?string $certificate, ?string $secret, ?string $otp): array
    {
        $headers = [
            'Accept-Version: ' . $this->version,
            'Content-Type: application/json',
            'Accept-Language: en',
            'Cache-Control: no-cache',
        ];

        if ($otp) {
            $headers[] = 'OTP: ' . $otp;
        }

        if ($certificate && $secret) {
            $cleanCert = $this->cleanCertificate($certificate);
            $credentials = base64_encode(base64_encode($cleanCert) . ':' . $secret);
            $headers[] = "Authorization: Basic {$credentials}";
        }

        return $headers;
    }

    private function cleanCertificate(string $certificate): string
    {
        $cleaned = str_replace('-----BEGIN CERTIFICATE-----', '', $certificate);
        $cleaned = str_replace('-----END CERTIFICATE-----', '', $cleaned);
        return trim($cleaned);
    }

    private function maskUrl(string $url): string
    {
        return preg_replace('/OTP=[^&]+/', 'OTP=***', $url);
    }
}
