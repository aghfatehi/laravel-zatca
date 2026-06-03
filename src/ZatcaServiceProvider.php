<?php

namespace Aghfatehi\Zatca;

use Aghfatehi\Zatca\Commands\ZatcaCheckCommand;
use Aghfatehi\Zatca\Commands\ZatcaOnboardCommand;
use Aghfatehi\Zatca\Commands\ZatcaSyncCommand;
use Aghfatehi\Zatca\Contracts\InvoiceSignerInterface;
use Aghfatehi\Zatca\Contracts\QrCodeGeneratorInterface;
use Aghfatehi\Zatca\Contracts\ZatcaClientInterface;
use Aghfatehi\Zatca\Enums\Environment;
use Aghfatehi\Zatca\Events\InvoiceCleared;
use Aghfatehi\Zatca\Events\InvoiceComplianceChecked;
use Aghfatehi\Zatca\Events\InvoiceFailed;
use Aghfatehi\Zatca\Events\InvoiceReported;
use Aghfatehi\Zatca\Listeners\LogZatcaEvent;
use Aghfatehi\Zatca\Logging\ZatcaLogger;
use Aghfatehi\Zatca\Services\ApiClient;
use Aghfatehi\Zatca\Services\CertificateService;
use Aghfatehi\Zatca\Services\InvoiceSignerService;
use Aghfatehi\Zatca\Services\Phase1Service;
use Aghfatehi\Zatca\Services\Phase2Service;
use Aghfatehi\Zatca\Services\QrCodeService;
use Aghfatehi\Zatca\Services\ZatcaService;
use Illuminate\Support\ServiceProvider;

class ZatcaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/zatca.php', 'zatca');

        $this->app->singleton(ZatcaLogger::class);
        $this->app->singleton(Environment::class, function () {
            return Environment::tryFrom(config('zatca.environment', 'sandbox')) ?? Environment::Sandbox;
        });

        $this->app->singleton(ApiClient::class, function ($app) {
            return new ApiClient(
                environment: $app->make(Environment::class),
                logger: $app->make(ZatcaLogger::class),
            );
        });

        $this->app->singleton(QrCodeService::class);
        $this->app->singleton(CertificateService::class, function ($app) {
            return new CertificateService($app->make(ZatcaLogger::class));
        });

        $this->app->singleton(InvoiceSignerService::class, function ($app) {
            return new InvoiceSignerService(
                qrService: $app->make(QrCodeService::class),
                certificateService: $app->make(CertificateService::class),
                logger: $app->make(ZatcaLogger::class),
            );
        });

        $this->app->singleton(Phase1Service::class, function ($app) {
            return new Phase1Service($app->make(QrCodeService::class));
        });

        $this->app->singleton(Phase2Service::class, function ($app) {
            return new Phase2Service(
                apiClient: $app->make(ApiClient::class),
                certificateService: $app->make(CertificateService::class),
                invoiceSigner: $app->make(InvoiceSignerService::class),
                logger: $app->make(ZatcaLogger::class),
            );
        });

        $this->app->singleton(ZatcaService::class, function ($app) {
            return new ZatcaService(
                phase1: $app->make(Phase1Service::class),
                phase2: $app->make(Phase2Service::class),
                qrService: $app->make(QrCodeService::class),
                logger: $app->make(ZatcaLogger::class),
            );
        });

        $this->app->singleton(ZatcaClient::class, function ($app) {
            return new ZatcaClient(
                service: $app->make(ZatcaService::class),
                phase1: $app->make(Phase1Service::class),
                phase2: $app->make(Phase2Service::class),
                qr: $app->make(QrCodeService::class),
            );
        });

        $this->app->alias(ZatcaClient::class, 'zatca');

        // Bind contracts
        $this->app->bind(QrCodeGeneratorInterface::class, QrCodeService::class);
        $this->app->bind(InvoiceSignerInterface::class, InvoiceSignerService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/zatca.php' => config_path('zatca.php'),
        ], 'zatca-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'zatca-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views/' => resource_path('views/vendor/zatca'),
        ], 'zatca-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ZatcaOnboardCommand::class,
                ZatcaSyncCommand::class,
                ZatcaCheckCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'zatca');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        $this->registerEvents();
    }

    private function registerEvents(): void
    {
        $this->app['events']->listen([
            InvoiceCleared::class,
            InvoiceReported::class,
            InvoiceComplianceChecked::class,
            InvoiceFailed::class,
        ], LogZatcaEvent::class);
    }
}
