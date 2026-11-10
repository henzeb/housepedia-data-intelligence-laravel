<?php

namespace Tests;

use Henzeb\Housepedia\Data\Intelligence\Laravel\HousepediaServiceProvider;
use Http\Mock\Client as MockClient;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    public MockClient $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new MockClient();
        $this->app->instance(HousepediaServiceProvider::HTTP_CLIENT, $this->http);
    }

    protected function getPackageProviders($app): array
    {
        return [
            HousepediaServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Housepedia' => \Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('housepedia.api_key', 'hp_laravel');
    }
}
