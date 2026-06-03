<?php

namespace Aghfatehi\Zatca\DTO;

class ComplianceResultDTO
{
    public function __construct(
        public readonly bool    $success,
        public readonly string  $requestID = '',
        public readonly string  $binarySecurityToken = '',
        public readonly string  $secret = '',
        public readonly ?string $errorMessage = null,
        public readonly ?array  $validationResults = null,
    ) {}

    public static function fromApiResponse(object $response): self
    {
        $hasError = isset($response->errors) || !isset($response->binarySecurityToken);

        return new self(
            success: !$hasError,
            requestID: $response->requestID ?? '',
            binarySecurityToken: $response->binarySecurityToken ?? '',
            secret: $response->secret ?? '',
            errorMessage: $response->errors[0]->message ?? null,
            validationResults: isset($response->validationResults) ? (array)$response->validationResults : null,
        );
    }

    public static function failed(string $message): self
    {
        return new self(
            success: false,
            errorMessage: $message,
        );
    }
}
