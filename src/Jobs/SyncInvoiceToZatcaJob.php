<?php

namespace Aghfatehi\Zatca\Jobs;

use Aghfatehi\Zatca\DTO\InvoiceDTO;
use Aghfatehi\Zatca\Events\InvoiceCleared;
use Aghfatehi\Zatca\Events\InvoiceFailed;
use Aghfatehi\Zatca\Events\InvoiceReported;
use Aghfatehi\Zatca\Logging\ZatcaLogger;
use Aghfatehi\Zatca\Services\Phase2Service;
use Aghfatehi\Zatca\Services\ZatcaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncInvoiceToZatcaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $invoiceData;
    public array $egsUnit;
    public string $certificate;
    public string $privateKey;
    public string $secret;

    public int $timeout;
    public int $tries;

    public function __construct(
        array $invoiceData,
        array $egsUnit,
        string $certificate,
        string $privateKey,
        string $secret,
    ) {
        $this->invoiceData = $invoiceData;
        $this->egsUnit = $egsUnit;
        $this->certificate = $certificate;
        $this->privateKey = $privateKey;
        $this->secret = $secret;

        $this->timeout = config('zatca.queue.timeout', 120);
        $this->tries = config('zatca.queue.tries', 3);
        $this->onQueue(config('zatca.queue.queue', 'zatca'));
    }

    public function handle(Phase2Service $phase2, ZatcaService $zatca, ZatcaLogger $logger): void
    {
        $logger->info('Job: Processing invoice sync', [
            'serial' => $this->invoiceData['invoice_serial_number'] ?? 'unknown',
            'attempt' => $this->attempts(),
        ]);

        try {
            $invoice = InvoiceDTO::fromArray($this->invoiceData);

            $signed = $phase2->signInvoice($invoice, $this->egsUnit, $this->certificate, $this->privateKey);

            $result = $phase2->submitInvoice(
                $signed['signed_xml'],
                $signed['invoice_hash'],
                $this->certificate,
                $this->secret,
            );

            if ($result->success) {
                if ($zatca->environment()->isProduction()) {
                    event(new InvoiceCleared($this->invoiceData, $result));
                } else {
                    event(new InvoiceReported($this->invoiceData, $result));
                }

                $logger->info('Job: Invoice synced successfully', [
                    'serial' => $this->invoiceData['invoice_serial_number'],
                ]);
            } else {
                throw new \RuntimeException($result->errorMessage ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            $logger->error('Job: Invoice sync failed', [
                'serial' => $this->invoiceData['invoice_serial_number'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            event(new InvoiceFailed($this->invoiceData, $e->getMessage()));

            if ($this->attempts() >= $this->tries) {
                $logger->error('Job: Max attempts reached for invoice', [
                    'serial' => $this->invoiceData['invoice_serial_number'],
                ]);
                return;
            }

            $this->release(config('zatca.queue.retry_delay_minutes', 60) * 60);
        }
    }

    public function failed(\Throwable $e): void
    {
        app(ZatcaLogger::class)->error('Job: Invoice sync permanently failed', [
            'serial' => $this->invoiceData['invoice_serial_number'] ?? 'unknown',
            'error' => $e->getMessage(),
        ]);
    }
}
