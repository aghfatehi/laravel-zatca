<?php

namespace Aghfatehi\Zatca\Enums;

enum InvoiceType: string
{
    case Invoice    = 'INVOICE';
    case CreditNote = 'CREDIT_NOTE';
    case DebitNote  = 'DEBIT_NOTE';

    public function ublCode(): int
    {
        return match ($this) {
            self::Invoice    => 388,
            self::CreditNote => 381,
            self::DebitNote  => 383,
        };
    }

    public function ublName(): string
    {
        return match ($this) {
            self::Invoice    => '0100000',
            self::CreditNote => '0110000',
            self::DebitNote  => '0111000',
        };
    }
}
