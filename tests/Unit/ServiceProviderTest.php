<?php

use Henzeb\Housepedia\Data\Intelligence\Client as CoreClient;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\MissingApiKeyException;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\HousepediaServiceProvider;
use Henzeb\Housepedia\Data\Intelligence\Laravel\HttpClientAdapter;
use Henzeb\Housepedia\Data\Intelligence\Transport\Transport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('merges the package configuration', function () {
    expect(config('housepedia.base_url'))->toBeNull()
        ->and(config('housepedia.timeout'))->toBe(30)
        ->and(config('housepedia.connect_timeout'))->toBe(10)
        ->and(config('housepedia.language'))->toBeNull()
        ->and(config('housepedia.retry.retries'))->toBe(2)
        ->and(config('housepedia.retry.service_unavailable'))->toBeFalse();
});

it('registers the clients as singletons', function () {
    expect(app(CoreClient::class))->toBeInstanceOf(CoreClient::class)
        ->toBe(app(CoreClient::class))
        ->and(app(Client::class))->toBeInstanceOf(Client::class)
        ->toBe(app(Client::class))
        ->and(app(Transport::class))->toBe(app(Transport::class));
});

it('wraps the registered core client', function () {
    expect(app(Client::class)->core())->toBe(app(CoreClient::class));
});

it('registers the http client adapter with the configured timeouts', function () {
    $this->app->forgetInstance(HousepediaServiceProvider::HTTP_CLIENT);
    config(['housepedia.timeout' => '5', 'housepedia.connect_timeout' => 2]);

    expect(app(HousepediaServiceProvider::HTTP_CLIENT))->toBeInstanceOf(HttpClientAdapter::class)
        ->and(sentOptions())->toMatchArray(['timeout' => 5.0, 'connect_timeout' => 2.0]);
});

it('reads the number of retries', function (mixed $retries, int $expected) {
    config(['housepedia.retry.retries' => $retries]);

    expect(app(Henzeb\Housepedia\Data\Intelligence\Transport\Transport::class)->config()->retries())->toBe($expected);
})->with([
    'negative' => [-1, 0],
    'numeric string' => ['3', 3],
    'invalid' => ['x', 2],
]);

it('falls back to default timeouts', function () {
    $this->app->forgetInstance(HousepediaServiceProvider::HTTP_CLIENT);
    config(['housepedia.timeout' => null, 'housepedia.connect_timeout' => 'x']);

    expect(sentOptions())->toMatchArray(['timeout' => 30.0, 'connect_timeout' => 10.0]);
});

/**
 * @return array<string, mixed>
 */
function sentOptions(): array
{
    $sent = [];

    Http::fake(function (Request $request, array $options) use (&$sent) {
        $sent = $options;

        return Http::response(jsonFixture('getAccount'));
    });

    app(Client::class)->account()->details();

    return $sent;
}

it('applies the configuration to requests', function () {
    config([
        'housepedia.api_key' => 'hp_configured',
        'housepedia.base_url' => 'https://sandbox.test',
        'housepedia.language' => 'nl',
    ]);
    $this->http->addResponse(jsonResponse(jsonFixture('getAccount')));

    app(Client::class)->account()->details();

    $request = lastRequest();

    expect((string)$request->getUri())->toBe('https://sandbox.test/v5/account')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer hp_configured')
        ->and($request->getHeaderLine('Accept-Language'))->toBe('nl');
});

it('uses defaults for empty configuration', function () {
    config(['housepedia.base_url' => '', 'housepedia.language' => '']);
    $this->http->addResponse(jsonResponse(jsonFixture('getAccount')));

    app(Client::class)->account()->details();

    $request = lastRequest();

    expect((string)$request->getUri())->toBe('https://huispedia.dev/v5/account')
        ->and($request->hasHeader('Accept-Language'))->toBeFalse();
});

it('throws without an api key', function (mixed $apiKey) {
    config(['housepedia.api_key' => $apiKey]);

    expect(fn () => app(Client::class)->account()->details())->toThrow(MissingApiKeyException::class)
        ->and($this->http->getRequests())->toBe([]);
})->with([null, '', 123]);

it('calls the catalog without an api key', function () {
    config(['housepedia.api_key' => null]);
    $this->http->addResponse(jsonResponse(jsonFixture('getCatalog')));

    app(Client::class)->catalog();

    expect(lastRequest()->hasHeader('Authorization'))->toBeFalse();
});

it('publishes the configuration', function () {
    $paths = HousepediaServiceProvider::pathsToPublish(HousepediaServiceProvider::class, 'housepedia-config');

    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__ . '/../../config/housepedia.php')])
        ->and(array_values($paths))->toBe([config_path('housepedia.php')]);
});

it('is registered through package discovery', function () {
    $composer = json_decode((string)file_get_contents(__DIR__ . '/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe([HousepediaServiceProvider::class])
        ->and($composer['extra']['laravel']['aliases'])->toBe(['Housepedia' => \Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia::class]);
});

it('reads the api version', function (mixed $version, string $expected) {
    config(['housepedia.version' => $version]);

    expect(app(Client::class)->version())->toBe($expected)
        ->and(app(CoreClient::class)->version())->toBe($expected);
})->with([
    'not set' => [null, 'V5'],
    'empty' => ['', 'V5'],
    'number' => [5, 'V5'],
    'string' => ['5', 'V5'],
    'prefixed' => ['v5', 'V5'],
]);

it('configures api version v5 by default', function () {
    expect(config('housepedia.version'))->toBe('v5');
});

it('throws for an unsupported api version', function () {
    config(['housepedia.version' => 'v6']);

    app(Client::class);
})->throws(Henzeb\Housepedia\Data\Intelligence\Exceptions\UnsupportedVersionException::class);
