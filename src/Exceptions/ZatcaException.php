<?php

namespace Aghfatehi\Zatca\Exceptions;

use Exception;

class ZatcaException extends Exception
{
    public function __construct(
        string $message = 'ZATCA integration error',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?array $context = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}
