<?php

use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Integration');

/**
 * @return array<string, mixed>
 */
function jsonFixture(string $name): array
{
    return json_decode((string)file_get_contents(__DIR__ . '/../resources/fixtures/v5/' . $name . '.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param array<array-key, mixed> $body
 * @param array<string, string> $headers
 */
function jsonResponse(array $body, int $status = 200, array $headers = []): ResponseInterface
{
    return new PsrResponse($status, $headers + ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
}

function respondWith(string $fixture): void
{
    test()->http->addResponse(jsonResponse(jsonFixture($fixture)));
}

function lastRequest(): RequestInterface
{
    $request = test()->http->getLastRequest();

    expect($request)->toBeInstanceOf(RequestInterface::class);

    return $request;
}

/**
 * @return array<string, string>
 */
function queryOf(RequestInterface $request): array
{
    parse_str($request->getUri()->getQuery(), $query);

    return $query;
}
