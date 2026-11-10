<?php

use Composer\InstalledVersions;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\NotFoundException;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\TransportException;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\HousepediaServiceProvider;
use Henzeb\Housepedia\Data\Intelligence\Laravel\NetworkException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\NetworkExceptionInterface;

beforeEach(function () {
    $this->app->forgetInstance(HousepediaServiceProvider::HTTP_CLIENT);
});

function sentRequest(): Request
{
    $recorded = Http::recorded();

    expect($recorded)->toHaveCount(1);

    return $recorded[0][0];
}

it('sends the request through laravel http client', function () {
    Http::fake(['huispedia.dev/*' => Http::response(jsonFixture('suggestAddresses'))]);

    $suggestions = Housepedia::addresses()->suggest('1076LJ 12-2');

    $request = sentRequest();

    expect($suggestions)->toBeInstanceOf(Collection::class)->not->toBeEmpty()
        ->and($request->method())->toBe('GET')
        ->and($request->url())->toBe('https://huispedia.dev/v5/addresses/suggest?q=1076LJ%2012-2')
        ->and($request->header('Authorization'))->toBe(['Bearer hp_laravel'])
        ->and($request->header('Accept'))->toBe(['application/json'])
        ->and($request->header('User-Agent'))->toBe(['henzeb/housepedia-data-intelligence/' . InstalledVersions::getPrettyVersion('henzeb/housepedia-data-intelligence')])
        ->and($request->header('Host'))->toBe(['huispedia.dev'])
        ->and($request->body())->toBe('');
});

it('sends the body with its content type', function () {
    Http::fake(['huispedia.dev/*' => Http::response(['data' => []])]);

    Housepedia::post('/v5/valuations/scenarios', ['rows' => [['postcode' => '1076LJ']]]);

    $request = sentRequest();

    expect($request->method())->toBe('POST')
        ->and($request->header('Content-Type'))->toBe(['application/json'])
        ->and($request->body())->toBe('{"rows":[{"postcode":"1076LJ"}]}');
});

it('sends head requests', function () {
    Http::fake(['huispedia.dev/*' => Http::response(null, 200)]);

    expect(Housepedia::properties()->withPostcode('1076LJ', 12, '2')->hasTransactions())->toBeTrue()
        ->and(sentRequest()->method())->toBe('HEAD');
});

it('uses a fake that is set up after the client was resolved', function () {
    $client = app(Client::class);

    Http::fake(['huispedia.dev/*' => Http::response(jsonFixture('getAccount'))]);

    $client->account()->details();

    expect(sentRequest()->url())->toBe('https://huispedia.dev/v5/account');
});

it('dispatches the http client events', function () {
    Event::fake([RequestSending::class, ResponseReceived::class]);
    Http::fake(['huispedia.dev/*' => Http::response(jsonFixture('getAccount'))]);

    Housepedia::account()->details();

    Event::assertDispatched(RequestSending::class, fn (RequestSending $event) => $event->request->url() === 'https://huispedia.dev/v5/account');
    Event::assertDispatched(ResponseReceived::class, fn (ResponseReceived $event) => $event->response->status() === 200);
});

it('leaves error statuses to the core', function () {
    Http::fake(['huispedia.dev/*' => Http::response(['title' => 'Not found', 'status' => 404, 'code' => 'not_found', 'request_id' => 'req_9'], 404)]);

    expect(fn () => Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find())
        ->toThrow(NotFoundException::class);
});

it('turns a failed connection into a transport exception', function () {
    Event::fake([ConnectionFailed::class]);
    Http::fake(fn () => throw new ConnectException('Connection refused', new PsrRequest('GET', 'https://huispedia.dev/v5/account')));

    try {
        Housepedia::account()->details();
        $this->fail('No exception was thrown.');
    } catch (TransportException $exception) {
        $previous = $exception->getPrevious();

        expect($exception->getMessage())->toBe('Connection refused')
            ->and($previous)->toBeInstanceOf(NetworkException::class)
            ->toBeInstanceOf(NetworkExceptionInterface::class)
            ->and($previous->getPrevious())->toBeInstanceOf(ConnectionException::class)
            ->and((string)$previous->getRequest()->getUri())->toBe('https://huispedia.dev/v5/account');
    }

    Event::assertDispatched(ConnectionFailed::class);
});

it('lets a stray request through', function () {
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response()]);

    expect(fn () => Housepedia::account()->details())->toThrow(StrayRequestException::class);
});
