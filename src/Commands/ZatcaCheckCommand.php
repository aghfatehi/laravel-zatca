<?php

namespace Aghfatehi\Zatca\Commands;

use Aghfatehi\Zatca\Logging\ZatcaLogger;
use Illuminate\Console\Command;

class ZatcaCheckCommand extends Command
{
    protected $signature = 'zatca:check
        {--openssl : Check OpenSSL availability}
        {--config : Display current configuration}';

    protected $description = 'Check ZATCA package readiness';

    public function handle(ZatcaLogger $logger): int
    {
        $this->info('ZATCA Package Readiness Check');
        $this->newLine();

        if ($this->option('openssl')) {
            $this->checkOpenSsl();
        }

        if ($this->option('config')) {
            $this->displayConfig();
        }

        if (!$this->option('openssl') && !$this->option('config')) {
            $this->checkOpenSsl();
            $this->newLine();
            $this->displayConfig();
        }

        return self::SUCCESS;
    }

    private function checkOpenSsl(): void
    {
        $this->info('Checking OpenSSL...');

        $version = shell_exec('openssl version 2>&1');

        if ($version) {
            $this->info("OpenSSL: {$version}");

            $curves = shell_exec('openssl ecparam -list_curves 2>&1');
            if ($curves && str_contains($curves, 'secp256k1')) {
                $this->info('secp256k1 curve: Available');
            } else {
                $this->warn('secp256k1 curve: Not found');
            }
        } else {
            $this->error('OpenSSL is not available or not in PATH');
        }
    }

    private function displayConfig(): void
    {
        $this->info('Current Configuration:');
        $this->line("  Phase:       " . config('zatca.phase', 'not set'));
        $this->line("  Environment: " . config('zatca.environment', 'not set'));
        $this->line("  Queue:       " . config('zatca.queue.connection', 'sync'));
        $this->line("  Queue Name:  " . config('zatca.queue.queue', 'zatca'));
        $this->line("  Logging:     " . (config('zatca.logging.enabled') ? 'enabled' : 'disabled'));

        $endpoint = config('zatca.api.' . config('zatca.environment') . '.base');
        $this->line("  API URL:     " . ($endpoint ?? 'not set'));
    }
}
