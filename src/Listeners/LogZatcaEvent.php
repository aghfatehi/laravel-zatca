<?php

namespace Aghfatehi\Zatca\Listeners;

use Aghfatehi\Zatca\Events\InvoiceCleared;
use Aghfatehi\Zatca\Events\InvoiceComplianceChecked;
use Aghfatehi\Zatca\Events\InvoiceFailed;
use Aghfatehi\Zatca\Events\InvoiceReported;
use Aghfatehi\Zatca\Logging\ZatcaLogger;

class LogZatcaEvent
{
    public function __construct(
        private ZatcaLogger $logger,
    ) {}

    public function handle(object $event): void
    {
        $serial = $event->invoiceData['invoice_serial_number'] ?? 'unknown';

        match (true) {
            $event instanceof InvoiceCleared => $this->logger->info("Invoice cleared: {$serial}"),
            $event instanceof InvoiceReported => $this->logger->info("Invoice reported: {$serial}"),
            $event instanceof InvoiceComplianceChecked => $this->logger->info("Compliance checked: {$serial}"),
            $event instanceof InvoiceFailed => $this->logger->error("Invoice failed: {$serial} - {$event->errorMessage}"),
            default => null,
        };
    }
}
