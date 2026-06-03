<?php

namespace Aghfatehi\Zatca\Events;

use Aghfatehi\Zatca\DTO\ComplianceResultDTO;
use Illuminate\Foundation\Events\Dispatchable;

class InvoiceCleared
{
    use Dispatchable;

    public function __construct(
        public array $invoiceData,
        public ComplianceResultDTO $result,
    ) {}
}
