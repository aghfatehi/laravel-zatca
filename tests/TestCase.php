<?php

namespace Aghfatehi\Zatca\Tests;

use Aghfatehi\Zatca\ZatcaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ZatcaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('zatca.phase', 'both');
        $app['config']->set('zatca.environment', 'sandbox');
        $app['config']->set('zatca.logging.enabled', false);
    }
}
