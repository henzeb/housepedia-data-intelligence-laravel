<?php

use Henzeb\Housepedia\Data\Intelligence\V5\DTO\AddressSuggestion;
use Henzeb\Housepedia\Data\Intelligence\V5\DTO\Estimate;
use Henzeb\Housepedia\Data\Intelligence\V5\DTO\Valuation;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Addresses;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Properties;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Response;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;

beforeEach(function () {
    Mockery::getConfiguration()->allowMockingNonExistentMethods(false);
});

afterEach(function () {
    Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
});

it('mocks the facade with shouldReceive', function () {
    $suggestion = Mockery::mock(AddressSuggestion::class);
    $suggestion->shouldReceive('label')->andReturn('Amazonenstraat 12-2, 1076LJ Amsterdam');

    $addresses = Mockery::mock(Addresses::class);
    $addresses->shouldReceive('suggest')->once()->with('1076LJ 12-2')->andReturn(new Results([$suggestion]));

    Housepedia::shouldReceive('addresses')->once()->andReturn($addresses);

    expect(Housepedia::addresses()->suggest('1076LJ 12-2')->first()->label())
        ->toBe('Amazonenstraat 12-2, 1076LJ Amsterdam')
        ->and($this->http->getRequests())->toBeEmpty();
});

it('mocks a chain through a locator', function () {
    $estimate = Mockery::mock(Estimate::class);
    $estimate->shouldReceive('value')->andReturn(450000);

    $valuation = Mockery::mock(Valuation::class);
    $valuation->shouldReceive('valuation')->andReturn($estimate);

    Housepedia::shouldReceive('properties->withPostcode->valuation')->andReturn($valuation);

    expect(Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation()->valuation()->value())->toBe(450000)
        ->and($this->http->getRequests())->toBeEmpty();
});

it('mocks a resource with its locator arguments', function () {
    $valuation = Mockery::mock(Valuation::class);

    $properties = Mockery::mock(Properties::class);
    $properties->shouldReceive('withPostcode')->once()->with('1076LJ', 12, '2')->andReturnSelf();
    $properties->shouldReceive('valuation')->once()->andReturn($valuation);

    Housepedia::shouldReceive('properties')->andReturn($properties);

    expect(Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation())->toBe($valuation);
});

it('spies on the facade', function () {
    $spy = Housepedia::spy();

    Housepedia::properties();

    expect($spy)->toBeInstanceOf(Client::class);
    $spy->shouldHaveReceived('properties')->once();
});

it('partially mocks the facade', function () {
    respondWith('suggestAddresses');

    $response = Mockery::mock(Response::class);
    $partial = Mockery::mock(app(Client::class));
    $partial->shouldReceive('get')->with('/v5/account')->andReturn($response);
    Housepedia::swap($partial);

    expect(Housepedia::get('/v5/account'))->toBe($response)
        ->and(Housepedia::addresses()->suggest('1076LJ 12-2')->first())->toBeInstanceOf(AddressSuggestion::class);
});
