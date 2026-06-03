<?php

namespace Aghfatehi\Zatca\Commands;

use Aghfatehi\Zatca\Jobs\SyncInvoiceToZatcaJob;
use Aghfatehi\Zatca\Logging\ZatcaLogger;
use Illuminate\Console\Command;

class ZatcaSyncCommand extends Command
{
    protected $signature = 'zatca:sync
        {--invoice= : Single invoice serial number to sync}
        {--all : Sync all pending invoices}
        {--force : Skip compliance check, force submit}';

    protected $description = 'Sync pending invoices to ZATCA FATOORA platform';

    public function handle(ZatcaLogger $logger): int
    {
        $this->info('ZATCA Invoice Sync');
        $this->newLine();

        $certificate = config('zatca.certificate') ?? base64_decode(env('ZATCA_CERTIFICATE', ''));
        $privateKey = config('zatca.private_key') ?? base64_decode(env('ZATCA_PRIVATE_KEY', ''));
        $secret = config('zatca.secret') ?? env('ZATCA_SECRET', '');

        if (empty($certificate) || empty($privateKey) || empty($secret)) {
            $this->error('ZATCA credentials not configured. Run php artisan zatca:onboard first.');
            $this->line('Or set ZATCA_CERTIFICATE, ZATCA_PRIVATE_KEY, and ZATCA_SECRET in your .env file.');

            return self::FAILURE;
        }

        $this->info('Using environment: ' . config('zatca.environment', 'sandbox'));
        $this->newLine();

        $egsUnit = config('zatca.egs');

        if ($this->option('invoice')) {
            $this->syncSingleInvoice($this->option('invoice'), $egsUnit, $certificate, $privateKey, $secret);
        } elseif ($this->option('all')) {
            $this->syncAllPendingInvoices($egsUnit, $certificate, $privateKey, $secret);
        } else {
            $this->error('Specify --invoice=SERIAL or --all');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function syncSingleInvoice(string $serial, array $egsUnit, string $certificate, string $privateKey, string $secret): void
    {
        $this->info("Dispatching sync job for invoice: {$serial}");

        $invoiceData = [
            'invoice_serial_number' => $serial,
            'invoice_counter_number' => 1,
            'issue_date' => date('Y-m-d'),
            'issue_time' => date('H:i:s'),
            'currency' => 'SAR',
            'previous_invoice_hash' => '',
            'invoice_type' => 'INVOICE',
            'line_items' => [],
        ];

        SyncInvoiceToZatcaJob::dispatch(
            invoiceData: $invoiceData,
            egsUnit: $egsUnit,
            certificate: $certificate,
            privateKey: $privateKey,
            secret: $secret,
        );

        $this->info('Job dispatched to queue: ' . config('zatca.queue.queue', 'zatca'));
    }

    private function syncAllPendingInvoices(array $egsUnit, string $certificate, string $privateKey, string $secret): void
    {
        $this->info('Syncing all pending invoices...');
        $this->warn('This command dispatches generic pending invoices. Implement your own invoice retrieval logic.');
        $this->warn('Override this command or listen to your model events to dispatch SyncInvoiceToZatcaJob.');
    }
}
