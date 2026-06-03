<?php

namespace Aghfatehi\Zatca\Events;

use Illuminate\Foundation\Events\Dispatchable;

class InvoiceFailed
{
    use Dispatchable;

    public function __construct(
        public array $invoiceData,
        public string $errorMessage,
    ) {}
}
