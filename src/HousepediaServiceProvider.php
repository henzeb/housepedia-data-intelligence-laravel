<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel;

use Carbon\CarbonInterval;
use Closure;
use GuzzleHttp\Psr7\HttpFactory;
use Henzeb\Housepedia\Data\Intelligence\Client as CoreClient;
use Henzeb\Housepedia\Data\Intelligence\Config;
use Henzeb\Housepedia\Data\Intelligence\Response as CoreResponse;
use Henzeb\Housepedia\Data\Intelligence\Transport\Transport;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

class HousepediaServiceProvider extends ServiceProvider
{
    public const string HTTP_CLIENT = 'housepedia.http-client';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/housepedia.php', 'housepedia');

        $this->app->singleton(self::HTTP_CLIENT, function (Application $app): ClientInterface {
            $config = $app->make(Repository::class);

            return new HttpClientAdapter(
                $app->make(Factory::class),
                $this->number($config->get('housepedia.timeout'), 30),
                $this->number($config->get('housepedia.connect_timeout'), 10),
            );
        });

        $this->app->singleton(Transport::class, function (Application $app): Transport {
            $config = $app->make(Repository::class);
            $factory = new HttpFactory();
            $client = $app->make(self::HTTP_CLIENT);

            assert($client instanceof ClientInterface);

            return new Transport(
                $this->string($config->get('housepedia.api_key')),
                $client,
                $factory,
                $factory,
                new Config(
                    baseUrl: $this->string($config->get('housepedia.base_url')),
                    language: $this->string($config->get('housepedia.language')),
                    retries: $this->retries($config->get('housepedia.retry.retries')),
                    retryServiceUnavailable: filter_var($config->get('housepedia.retry.service_unavailable'), FILTER_VALIDATE_BOOLEAN),
                    cache: $this->cache($app, $config),
                    cachePrefix: $this->string($config->get('housepedia.cache.prefix')) ?? 'housepedia-data-intelligence',
                    maxCacheAgeInSeconds: $this->maxCacheAge($config->get('housepedia.cache.max_age_in_seconds')),
                    version: $this->version($config->get('housepedia.version')),
                ),
            );
        });

        $this->app->singleton(
            CoreClient::class,
            fn (Application $app): CoreClient => new CoreClient($app->make(Transport::class)),
        );

        $this->app->singleton(
            Client::class,
            fn (Application $app): Client => new Client($app->make(CoreClient::class)),
        );
    }

    public function boot(): void
    {
        CoreClient::retryUsing(
            fn (Closure $attempt, Config $config): CoreResponse => retry(
                $config->retries() + 1,
                $attempt,
                fn (int $attempts, Throwable $exception): CarbonInterval => CarbonInterval::seconds($config->retryDelay($exception)),
                $config->shouldRetry(...),
            ),
        );

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../config/housepedia.php' => $this->app->configPath('housepedia.php')],
                'housepedia-config',
            );
        }
    }

    private function version(mixed $value): int|string|null
    {
        return is_int($value) ? $value : $this->string($value);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function cache(Application $app, Repository $config): ?CacheInterface
    {
        if (!filter_var($config->get('housepedia.cache.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return $app->make(CacheFactory::class)->store($this->string($config->get('housepedia.cache.store')));
    }

    private function maxCacheAge(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int)$value) : null;
    }

    private function retries(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int)$value) : 2;
    }

    private function number(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float)$value : $default;
    }
}
