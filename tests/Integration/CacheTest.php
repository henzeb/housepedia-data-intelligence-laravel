<?php

use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;
use Henzeb\Housepedia\Data\Intelligence\Transport\Transport;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->http->setDefaultResponse(jsonResponse(jsonFixture('getEnergyLabel'), 200, ['Cache-Control' => 'private, max-age=3600']));
});

function enableHousepediaCache(array $settings = []): void
{
    config(['housepedia.cache' => $settings + ['enabled' => true, 'store' => 'array']]);
}

it('does not cache by default', function () {
    Housepedia::properties()->withPostcode('1076LJ', 12, '2')->energyLabel();
    Housepedia::properties()->withPostcode('1076LJ', 12, '2')->energyLabel();

    expect($this->http->getRequests())->toHaveCount(2)
        ->and(app(Transport::class)->config()->cache())->toBeNull();
});

it('caches answers in the configured store', function () {
    enableHousepediaCache();

    Housepedia::properties()->withPostcode('1076LJ', 12, '2')->energyLabel();
    Housepedia::properties()->withPostcode('1076LJ', 12, '2')->energyLabel();

    expect($this->http->getRequests())->toHaveCount(1)
        ->and(app(Transport::class)->config()->cache())->toBe(Cache::store('array'));
});

it('uses the default store without a store name', function (mixed $store) {
    enableHousepediaCache(['store' => $store]);

    expect(app(Transport::class)->config()->cache())->toBe(Cache::store());
})->with([null, '', 123]);

it('reads enabled as a boolean', function (mixed $enabled, bool $expected) {
    enableHousepediaCache(['enabled' => $enabled]);

    expect(app(Transport::class)->config()->cache() !== null)->toBe($expected);
})->with([
    [true, true],
    ['true', true],
    ['1', true],
    [false, false],
    ['false', false],
    [null, false],
]);

it('uses the configured prefix', function (mixed $prefix, string $expected) {
    enableHousepediaCache(['prefix' => $prefix]);

    expect(app(Transport::class)->config()->cachePrefix())->toBe($expected);
})->with([
    ['my-app', 'my-app'],
    ['', 'housepedia-data-intelligence'],
    [null, 'housepedia-data-intelligence'],
]);

it('reads the maximum age in seconds', function (mixed $maxAge, ?int $expected) {
    enableHousepediaCache(['max_age_in_seconds' => $maxAge]);

    expect(app(Transport::class)->config()->maxCacheAgeInSeconds())->toBe($expected);
})->with([
    [600, 600],
    ['600', 600],
    [-5, 0],
    [null, null],
    ['', null],
    ['abc', null],
]);

it('skips the cache per call', function () {
    enableHousepediaCache();
    $home = Housepedia::properties()->withPostcode('1076LJ', 12, '2');

    $home->withoutCache()->energyLabel();
    $home->withoutCache()->energyLabel();
    Housepedia::withoutCache()->get('/v5/properties/1076LJ-12-2/energy-label');

    expect($this->http->getRequests())->toHaveCount(3)
        ->and($home->withoutCache())->toBeInstanceOf(Resources\Properties::class)->not->toBe($home);
});

it('sets the maximum age per call', function () {
    enableHousepediaCache(['max_age_in_seconds' => 0]);
    $home = Housepedia::properties()->withPostcode('1076LJ', 12, '2');

    $home->energyLabel();
    $home->withMaxCacheAge(60)->energyLabel();
    $home->withMaxCacheAge(60)->energyLabel();
    Housepedia::withMaxCacheAge(60)->properties()->withPostcode('1076LJ', 12, '2')->energyLabel();

    expect($this->http->getRequests())->toHaveCount(2)
        ->and($home->withMaxCacheAge(60))->toBeInstanceOf(Resources\Properties::class)->not->toBe($home);
});

it('returns new wrappers with the cache settings', function (Closure $resource, string $class) {
    $wrapper = $resource();

    expect($wrapper->withoutCache())->toBeInstanceOf($class)->not->toBe($wrapper)
        ->and($wrapper->withMaxCacheAge(60))->toBeInstanceOf($class)->not->toBe($wrapper);
})->with([
    [fn () => app(Client::class), Client::class],
    [fn () => Housepedia::addresses(), Resources\Addresses::class],
    [fn () => Housepedia::properties(), Resources\Properties::class],
    [fn () => Housepedia::buildings(), Resources\Buildings::class],
    [fn () => Housepedia::areas(), Resources\Areas::class],
    [fn () => Housepedia::areas()->withPostcode4('1076'), Resources\PostcodeArea::class],
    [fn () => Housepedia::valuations(), Resources\Valuations::class],
    [fn () => Housepedia::account(), Resources\Account::class],
    [fn () => Housepedia::account()->lazy(), Resources\LazyAccount::class],
]);
