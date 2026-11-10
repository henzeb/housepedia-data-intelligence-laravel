<?php

use Henzeb\Housepedia\Data\Intelligence\Exceptions\RateLimitException;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\ServiceUnavailableException;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
});

function rateLimited(string $retryAfter = '1'): Psr\Http\Message\ResponseInterface
{
    return jsonResponse(['code' => 'rate_limited', 'title' => 'Too Many Requests'], 429, ['Retry-After' => $retryAfter]);
}

function unavailable(): Psr\Http\Message\ResponseInterface
{
    return jsonResponse(['code' => 'upstream_unavailable', 'title' => 'Service Unavailable'], 503);
}

it('retries rate limited requests twice by default', function () {
    $this->http->addResponse(rateLimited('3'));
    $this->http->addResponse(rateLimited());
    $this->http->addResponse(jsonResponse(jsonFixture('getAccount')));

    app(Client::class)->account()->details();

    expect($this->http->getRequests())->toHaveCount(3);
    Sleep::assertSequence([Sleep::for(3000)->milliseconds(), Sleep::for(1000)->milliseconds()]);
});

it('throws after the configured retries', function (mixed $retries, int $requests) {
    config(['housepedia.retry.retries' => $retries]);

    for ($i = 0; $i < $requests; $i++) {
        $this->http->addResponse(rateLimited());
    }

    expect(fn () => app(Client::class)->account()->details())->toThrow(RateLimitException::class)
        ->and($this->http->getRequests())->toHaveCount($requests);
    Sleep::assertSleptTimes($requests - 1);
})->with([
    'none' => [0, 1],
    'numeric string' => ['1', 2],
    'invalid' => ['x', 3],
    'negative' => [-1, 1],
]);

it('does not retry an unavailable service by default', function () {
    $this->http->addResponse(unavailable());

    expect(fn () => app(Client::class)->account()->details())->toThrow(ServiceUnavailableException::class)
        ->and($this->http->getRequests())->toHaveCount(1);
    Sleep::assertNeverSlept();
});

it('retries an unavailable service when enabled', function (mixed $enabled) {
    config(['housepedia.retry.service_unavailable' => $enabled]);
    $this->http->addResponse(unavailable());
    $this->http->addResponse(jsonResponse(jsonFixture('getAccount')));

    app(Client::class)->account()->details();

    expect($this->http->getRequests())->toHaveCount(2);
    Sleep::assertSequence([Sleep::for(1000)->milliseconds()]);
})->with([true, 'true', '1', 'on', 'yes']);
