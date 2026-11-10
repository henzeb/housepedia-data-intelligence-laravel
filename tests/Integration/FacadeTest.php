<?php

use Henzeb\Housepedia\Data\Intelligence\V5\DTO\Address;
use Henzeb\Housepedia\Data\Intelligence\V5\DTO\AddressSuggestion;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Illuminate\Support\Collection;

it('resolves the laravel client', function () {
    expect(Housepedia::getFacadeRoot())->toBeInstanceOf(Client::class)
        ->toBe(app(Client::class));
});

it('returns collections of dtos through the facade', function () {
    respondWith('suggestAddresses');

    $suggestions = Housepedia::addresses()->suggest('1076LJ 12-2');

    expect($suggestions)->toBeInstanceOf(Collection::class)
        ->and($suggestions->first())->toBeInstanceOf(AddressSuggestion::class)
        ->and(queryOf(lastRequest()))->toBe(['q' => '1076LJ 12-2'])
        ->and(lastRequest()->getHeaderLine('Authorization'))->toBe('Bearer hp_laravel');
});

it('returns single dtos through the facade', function () {
    respondWith('getAddress');

    expect(Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find())->toBeInstanceOf(Address::class)
        ->and(lastRequest()->getUri()->getPath())->toBe('/v5/addresses/1076LJ-12-2');
});

it('resolves through the alias', function () {
    respondWith('suggestAddresses');

    expect(\Housepedia::addresses()->suggest('1076LJ 12-2'))->toBeInstanceOf(Collection::class);
});

it('sets the api version on a copy', function () {
    respondWith('suggestAddresses');

    $client = Housepedia::withVersion('v5');
    $client->addresses()->suggest('1076LJ');

    expect($client)->not->toBe(Housepedia::getFacadeRoot())
        ->and($client->version())->toBe('V5')
        ->and(Housepedia::version())->toBe('V5')
        ->and(lastRequest()->getUri()->getPath())->toBe('/v5/addresses/suggest');
});
