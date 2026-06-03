<?php

namespace Aghfatehi\Zatca\Exceptions;

class ComplianceException extends ZatcaException
{
    public function __construct(
        string $message = 'Invoice compliance check failed',
        ?array $context = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
