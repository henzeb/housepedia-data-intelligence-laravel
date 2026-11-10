<?php

use Henzeb\Housepedia\Data\Intelligence\Exceptions\AddressAmbiguousException;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\MissingLocatorException;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\NotFoundException;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\RateLimitException;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Addresses;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Areas;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Buildings;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\PostcodeArea;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Properties;

it('keeps forwarded builders immutable', function () {
    $this->http->setDefaultResponse(jsonResponse(jsonFixture('getProperty')));

    $properties = Housepedia::properties();
    $first = $properties->withPostcode('1076LJ', 12, '2');
    $second = $properties->withPostcode('2511BT', 10);

    $first->find();
    $second->find();

    expect($first)->toBeInstanceOf(Properties::class)->not->toBe($properties)
        ->and($second)->not->toBe($first)
        ->and(array_map(fn ($request) => $request->getUri()->getPath(), $this->http->getRequests()))
        ->toBe(['/v5/properties/1076LJ-12-2', '/v5/properties/2511BT-10'])
        ->and(fn () => $properties->find())->toThrow(MissingLocatorException::class);
});

it('keeps every wrapper immutable', function () {
    $addresses = Housepedia::addresses();
    $areas = Housepedia::areas();
    $buildings = Housepedia::buildings();

    expect($addresses->withPostcode('1076LJ', 12, '2'))->toBeInstanceOf(Addresses::class)->not->toBe($addresses)
        ->and($addresses->withBagId('0363200000028422'))->not->toBe($addresses)
        ->and($areas->withMunicipality('GM0363'))->toBeInstanceOf(Areas::class)->not->toBe($areas)
        ->and($areas->national())->not->toBe($areas)
        ->and($areas->withPostcode4('1076'))->toBeInstanceOf(PostcodeArea::class)
        ->and($areas->withPostcode6('1076LJ'))->toBeInstanceOf(PostcodeArea::class)
        ->and($buildings->withBagPandId('0363100012345678'))->toBeInstanceOf(Buildings::class)->not->toBe($buildings)
        ->and(fn () => $addresses->find())->toThrow(MissingLocatorException::class)
        ->and(fn () => $areas->stats())->toThrow(MissingLocatorException::class)
        ->and(fn () => $buildings->find())->toThrow(MissingLocatorException::class)
        ->and($this->http->getRequests())->toBe([]);
});

it('propagates core exceptions', function () {
    $this->http->addResponse(jsonResponse(['title' => 'Not found', 'status' => 404, 'code' => 'not_found', 'request_id' => 'req_9'], 404));

    try {
        Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation();
        $this->fail('No exception thrown');
    } catch (NotFoundException $exception) {
        expect($exception->requestId())->toBe('req_9')
            ->and(get_class($exception))->toBe(NotFoundException::class);
    }
});

it('propagates rate limits and ambiguous addresses', function () {
    config(['housepedia.retry.retries' => 0]);
    $this->http->addResponse(jsonResponse(['title' => 'Slow', 'status' => 429, 'code' => 'rate_limited'], 429, ['Retry-After' => '7']));
    $this->http->addResponse(jsonResponse(['title' => 'Ambiguous', 'status' => 409, 'code' => 'address_ambiguous', 'errors' => [['postcode' => '1076LJ', 'house_number' => 12, 'house_number_ext' => '1']]], 409));

    expect(fn () => Housepedia::addresses()->suggest('1076LJ'))->toThrow(RateLimitException::class);

    try {
        Housepedia::addresses()->withPostcode('1076LJ', 12)->find();
        $this->fail('No exception thrown');
    } catch (AddressAmbiguousException $exception) {
        expect($exception->candidates()[0]->houseNumberExt())->toBe('1');
    }
});

it('sends one request per call', function () {
    $this->http->setDefaultResponse(jsonResponse(jsonFixture('suggestAddresses')));

    Housepedia::addresses()->suggest('1076LJ 12-2');
    Housepedia::addresses()->suggest('1076LJ 4-2');

    expect($this->http->getRequests())->toHaveCount(2);
});
