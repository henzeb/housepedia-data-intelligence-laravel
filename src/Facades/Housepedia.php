<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Facades;

use Henzeb\Housepedia\Data\Intelligence\Client as CoreClient;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Catalog\Catalog;
use Closure;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\HousepediaServiceProvider;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Account;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Addresses;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Areas;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Buildings;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Properties;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Valuations;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Response;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\Endpoint;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\FakeResponse;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\HousepediaFake;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\RecordedRequest;
use Henzeb\Housepedia\Data\Intelligence\Transport\Transport;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * @method static Addresses addresses()
 * @method static Properties properties()
 * @method static Buildings buildings()
 * @method static Areas areas()
 * @method static Valuations valuations()
 * @method static Account account()
 * @method static Catalog catalog()
 * @method static Response get(string $path, array<string, string|int|float|bool|null> $query = [])
 * @method static Response post(string $path, array<array-key, mixed> $body, array<string, string|int|float|bool|null> $query = [])
 * @method static string version()
 * @method static Client withVersion(int|string $version)
 * @method static Client withMaxCacheAge(int $seconds)
 * @method static Client withoutCache()
 * @method static CoreClient core()
 *
 * @see Client
 */
class Housepedia extends Facade
{
    /**
     * @param array<string, array<array-key, mixed>|FakeResponse|Closure> $responses
     */
    public static function fake(array $responses = []): HousepediaFake
    {
        $fake = new HousepediaFake($responses);
        $container = Container::getInstance();

        $container->instance(HousepediaServiceProvider::HTTP_CLIENT, $fake);
        $container->forgetInstance(Transport::class);
        $container->forgetInstance(CoreClient::class);
        $container->forgetInstance(Client::class);

        static::clearResolvedInstance(Client::class);

        return $fake;
    }

    /**
     * @param Closure(RecordedRequest): bool|null $callback
     */
    public static function assertSent(Endpoint $endpoint, ?Closure $callback = null): void
    {
        self::housepediaFake()->assertSent($endpoint, $callback);
    }

    /**
     * @param Closure(RecordedRequest): bool|null $callback
     */
    public static function assertNotSent(Endpoint $endpoint, ?Closure $callback = null): void
    {
        self::housepediaFake()->assertNotSent($endpoint, $callback);
    }

    public static function assertSentCount(int $count, ?Endpoint $endpoint = null): void
    {
        self::housepediaFake()->assertSentCount($count, $endpoint);
    }

    public static function assertNothingSent(): void
    {
        self::housepediaFake()->assertNothingSent();
    }

    /**
     * @param Closure(RecordedRequest): bool|null $callback
     * @return Collection<int, RecordedRequest>
     */
    public static function recorded(?Endpoint $endpoint = null, ?Closure $callback = null): Collection
    {
        return self::housepediaFake()->recorded($endpoint, $callback);
    }

    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }

    private static function housepediaFake(): HousepediaFake
    {
        $client = Container::getInstance()->make(HousepediaServiceProvider::HTTP_CLIENT);

        if (! $client instanceof HousepediaFake) {
            throw new RuntimeException('Call Housepedia::fake() before asserting Housepedia requests.');
        }

        return $client;
    }
}
