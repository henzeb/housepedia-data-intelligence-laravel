<?php

use Henzeb\Housepedia\Data\Intelligence\V5\DTO\RequestLogEntry;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\LazyAccount;
use Illuminate\Support\LazyCollection;
use Psr\Http\Message\RequestInterface;

/**
 * @param list<string> $requestIds
 */
function requestLogPage(array $requestIds, ?string $nextCursor): void
{
    test()->http->addResponse(jsonResponse([
        'data' => array_map(fn (string $requestId) => [
            'request_id' => $requestId,
            'at' => '2026-10-26T12:00:00+00:00',
            'method' => 'GET',
            'path' => '/v5/account',
            'status' => 200,
            'credits' => 0,
        ], $requestIds),
        'meta' => ['request_id' => 'req_page', 'next_cursor' => $nextCursor],
    ]));
}

it('returns the lazy account wrapper', function () {
    expect(Housepedia::account()->lazy())->toBeInstanceOf(LazyAccount::class);
});

it('returns every entry of every page as a lazy collection', function () {
    requestLogPage(['req_1', 'req_2'], 'cursor_1');
    requestLogPage(['req_3'], null);

    $entries = Housepedia::account()->lazy()->requests(new DateTimeImmutable('2026-10-01T00:00:00+00:00'), null, 'key_1', 404, 2);

    expect($entries)->toBeInstanceOf(LazyCollection::class)
        ->and($this->http->getRequests())->toBe([]);

    $all = $entries->all();

    $filters = ['from' => '2026-10-01T00:00:00+00:00', 'key_id' => 'key_1', 'status' => '404', 'limit' => '2'];

    expect($all)->each->toBeInstanceOf(RequestLogEntry::class)
        ->and(array_keys($all))->toBe([0, 1, 2])
        ->and(array_map(fn (RequestLogEntry $entry) => $entry->requestId(), $all))->toBe(['req_1', 'req_2', 'req_3'])
        ->and(array_map(fn (RequestInterface $request) => $request->getUri()->getPath(), $this->http->getRequests()))
        ->toBe(['/v5/account/requests', '/v5/account/requests'])
        ->and(array_map(queryOf(...), $this->http->getRequests()))->toBe([$filters, $filters + ['cursor' => 'cursor_1']]);
});

it('stops at the page it needs', function () {
    requestLogPage(['req_1', 'req_2'], 'cursor_1');
    requestLogPage(['req_3'], null);

    expect(Housepedia::account()->lazy()->requests()->first()->requestId())->toBe('req_1')
        ->and($this->http->getRequests())->toHaveCount(1);
});

it('starts at the first page on every enumeration', function () {
    requestLogPage(['req_1'], null);
    requestLogPage(['req_1'], null);

    $entries = Housepedia::account()->lazy()->requests();

    $entries->all();
    $entries->all();

    expect(array_map(queryOf(...), $this->http->getRequests()))->toBe([[], []]);
});
