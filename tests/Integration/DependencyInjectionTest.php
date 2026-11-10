<?php

use Henzeb\Housepedia\Data\Intelligence\Client as CoreClient;
use Henzeb\Housepedia\Data\Intelligence\V5\DTO\AddressSuggestion;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Illuminate\Support\Collection;

readonly class AddressService
{
    public function __construct(
        private Client $client,
    ) {
    }

    public function suggestions(): Collection
    {
        return $this->client->addresses()->suggest('1076LJ 12-2');
    }
}

readonly class CoreAddressService
{
    public function __construct(
        public CoreClient $client,
    ) {
    }
}

it('injects the laravel client', function () {
    respondWith('suggestAddresses');

    $suggestions = app(AddressService::class)->suggestions();

    expect($suggestions)->toBeInstanceOf(Collection::class)
        ->each->toBeInstanceOf(AddressSuggestion::class);
});

it('injects the core client', function () {
    respondWith('suggestAddresses');

    $suggestions = app(CoreAddressService::class)->client->addresses()->suggest('1076LJ 12-2');

    expect($suggestions->data())->toBeList()
        ->each->toBeInstanceOf(AddressSuggestion::class);
});
