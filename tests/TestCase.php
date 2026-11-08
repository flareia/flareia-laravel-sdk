<?php

namespace FlareIA\Sdk\Laravel\Tests;

use FlareIA\Sdk\Laravel\FlareIAServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FlareIAServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('flareia.api_key', 'test-key');
        $app['config']->set('flareia.base_url', 'https://flareia.test');
    }
}
