<?php

namespace Aghfatehi\Zatca\Commands;

use Aghfatehi\Zatca\Logging\ZatcaLogger;
use Aghfatehi\Zatca\Services\CertificateService;
use Aghfatehi\Zatca\Services\Phase2Service;
use Illuminate\Console\Command;

class ZatcaOnboardCommand extends Command
{
    protected $signature = 'zatca:onboard
        {--otp= : OTP received from ZATCA portal}
        {--solution-name=ERP : Solution/Application name}
        {--production : Use production environment}
        {--save : Save credentials to config}';

    protected $description = 'Onboard EGS unit with ZATCA and obtain compliance certificate';

    public function handle(Phase2Service $phase2, CertificateService $certService, ZatcaLogger $logger): int
    {
        $this->info('ZATCA Onboarding Wizard');
        $this->newLine();

        // @todo: replace raw array with EgsUnitDTO::fromArray() once DTO is wired into services
        $egsUnit = [
            'uuid' => $this->ask('EGS UUID', config('zatca.egs.uuid')),
            'custom_id' => $this->ask('EGS Custom ID', config('zatca.egs.uuid')),
            'model' => $this->ask('EGS Model', 'Desktop'),
            'vat_number' => $this->ask('VAT Number', config('zatca.egs.vat_number')),
            'vat_name' => $this->ask('VAT Name', config('zatca.egs.vat_name')),
            'crn_number' => $this->ask('CRN Number', config('zatca.egs.crn_number')),
            'location' => [
                'city' => $this->ask('City', config('zatca.egs.location.city')),
                'city_subdivision' => $this->ask('City Subdivision', config('zatca.egs.location.city_subdivision')),
                'street' => $this->ask('Street', config('zatca.egs.location.street')),
                'building' => $this->ask('Building Number', config('zatca.egs.location.building')),
                'plot_identification' => $this->ask('Plot Identification', config('zatca.egs.location.plot_identification')),
                'postal_zone' => $this->ask('Postal Zone', config('zatca.egs.location.postal_zone')),
            ],
            'branch_name' => $this->ask('Branch Name', config('zatca.egs.branch_name')),
            'branch_industry' => $this->ask('Branch Industry', config('zatca.egs.branch_industry')),
        ];

        $solutionName = $this->option('solution-name');

        $this->info('Generating EC key pair and CSR...');

        try {
            $keys = $phase2->generateKeysAndCsr($egsUnit, $solutionName);

            $this->info('Private Key generated successfully.');
            $this->line($keys['private_key']);
            $this->newLine();
            $this->info('CSR generated successfully.');
            $this->line($keys['csr']);
            $this->newLine();

            $otp = $this->option('otp') ?: $this->secret('Enter OTP from ZATCA portal');

            $this->info('Issuing compliance certificate...');

            $result = $phase2->issueComplianceCertificate($keys['csr'], $otp);

            if ($result->success) {
                $this->info('Compliance certificate issued successfully!');
                $this->newLine();
                $this->info('Certificate:');
                $this->line($result->binarySecurityToken);
                $this->newLine();
                $this->info("Secret: {$result->secret}");

                if ($this->option('save')) {
                    $this->saveCredentials($result->binarySecurityToken, $result->secret, $keys['private_key']);
                }
            } else {
                $this->error('Failed: ' . ($result->errorMessage ?? 'Unknown error'));
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $logger->error('Onboarding failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    private function saveCredentials(string $certificate, string $secret, string $privateKey): void
    {
        $envFile = base_path('.env');
        $content = file_get_contents($envFile);

        $replacements = [
            'ZATCA_CERTIFICATE' => base64_encode($certificate),
            'ZATCA_SECRET' => $secret,
            'ZATCA_PRIVATE_KEY' => base64_encode($privateKey),
        ];

        foreach ($replacements as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$value}", $content);
            } else {
                $content .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envFile, $content);
        $this->info('Credentials saved to .env file.');
    }
}
