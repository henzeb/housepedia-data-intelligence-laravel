## Housepedia Data Intelligence

This package integrates the Housepedia Data Intelligence API (https://huispedia.dev) into Laravel: home values,
property characteristics, WOZ values, energy labels, buildings and area statistics for addresses in the
Netherlands. It wraps `henzeb/housepedia-data-intelligence`; the full reference is in
`vendor/henzeb/housepedia-data-intelligence/docs/` (start with `agents.md`).

### Configuration

- Set `HOUSEPEDIA_API_KEY` in `.env`; without it only `catalog()` works and other calls throw
  `MissingApiKeyException`. `HOUSEPEDIA_VERSION` pins the API version (default `v5`); `Housepedia::withVersion()`
  sets it per call chain. Optional: `HOUSEPEDIA_LANGUAGE` (`nl` or `en` for error details),
  `HOUSEPEDIA_TIMEOUT`, `HOUSEPEDIA_CONNECT_TIMEOUT`, `HOUSEPEDIA_BASE_URL`.
- Rate-limited calls (429) are retried `HOUSEPEDIA_RETRIES` times (default 2, `0` disables) after the
  `Retry-After` seconds; `HOUSEPEDIA_RETRY_SERVICE_UNAVAILABLE=true` also retries a 503. The wait uses
  `Illuminate\Support\Sleep`, so call `Sleep::fake()` in tests that hit rate limits.
- `HOUSEPEDIA_CACHE=true` caches answers in a Laravel cache store (`HOUSEPEDIA_CACHE_STORE`, default store
  when empty, keys prefixed with `HOUSEPEDIA_CACHE_PREFIX`) for the time the API allows; `HOUSEPEDIA_CACHE_MAX_AGE` shortens it. Per call:
  `->withMaxCacheAge($seconds)` and `->withoutCache()` on a resource or `Housepedia::`.
- Publish the config with `php artisan vendor:publish --tag=housepedia-config`.

### Usage

- Use the `Housepedia` facade or inject `Henzeb\Housepedia\Data\Intelligence\Laravel\Client`.
- Resources are immutable builders: a `with*()` call returns a new instance.
- List endpoints return `Henzeb\Housepedia\Data\Intelligence\Laravel\Results`: a Laravel `Collection` of typed objects
  with `meta()` (request id, credits, `rateLimit()`); `reverse()` returns `ReverseResults`, which adds `areas()`.
  Single results return a typed object with `meta()`.
- `account()->requests()` returns one page of the request log; `account()->lazy()->requests()` returns every entry
  of every page as a Laravel `LazyCollection` that requests the next page when it reaches it.
- Getters spell units out: `distanceInMeters()`, `livingAreaInSquareMeters()`, `pricePerSquareMeter()`.
- Use the typed methods. `Housepedia::get()` and `Housepedia::post()` are the low-level request methods for
  endpoints without a typed method; `collect()` returns the body as a Collection.
- Prices are set by Housepedia (`Housepedia::catalog()`, https://huispedia.dev/en/docs/credits/). Enable `HOUSEPEDIA_CACHE`
  to reuse answers instead of caching them yourself.

@verbatim
<code-snippet name="Look up a home and value it" lang="php">
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;

$suggestions = Housepedia::addresses()->suggest('1076LJ 12-2');          // Results (a Laravel Collection)

$address = Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find();
$address->street();

$home = Housepedia::properties()->withPostcode('1076LJ', 12, '2');
$valuation = $home->valuation();
$woz = $home->woz();

$stats = Housepedia::areas()->withMunicipality('GM0363')->stats();
</code-snippet>
@endverbatim

### Errors

All API errors extend `Henzeb\Housepedia\Data\Intelligence\Exceptions\ApiException` (`status()`, `errorCode()`,
`detail()`, `retryAfter()`): `NotFoundException` (404), `AddressAmbiguousException` (409, `candidates()` lists
the homes), `DataUnavailableException` (422), `RateLimitException` (429), `ForbiddenException` (403, for example
transaction prices without a contract).

### Testing

Use `Housepedia::fake()`: every call answers an example response of its endpoint without a request, for the facade
and injected clients. Responses are keyed by the value of a `Testing\Endpoint` case (the catalog product id, such as
`valuation`). An array is merged into the example response (lists replace); `FakeResponse::json()` answers a body as
given; `FakeResponse::error($status, $code)` is thrown as the matching exception (`404` → `NotFoundException`); a
closure receives the `RecordedRequest`. Several responses passed to `respond()` answer in order, the last one repeats.
A path without an `Endpoint` case throws `StrayRequestException`. Call `Sleep::fake()` when faking a `429`.

@verbatim
<code-snippet name="Fake Housepedia in a test" lang="php">
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\Endpoint;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\FakeResponse;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\RecordedRequest;

$fake = Housepedia::fake([
    Endpoint::Valuation->value => ['valuation' => ['value' => 450000]],
]);

$fake->respond(Endpoint::PropertyWoz, FakeResponse::error(404));

// code under test

Housepedia::assertSent(Endpoint::Valuation, fn (RecordedRequest $request) => str_contains($request->path(), '1076LJ-12-2'));
Housepedia::assertNotSent(Endpoint::AddressSuggest);
Housepedia::assertSentCount(1, Endpoint::Valuation);
</code-snippet>
@endverbatim

`RecordedRequest` has `endpoint()`, `method()`, `url()`, `path()`, `query()`, `body()` and `header()`;
`Housepedia::recorded(Endpoint::Valuation)` returns them as a Collection. The fake replaces Laravel's HTTP client for
Housepedia, so `Http::fake()` does not see these requests.

Without `Housepedia::fake()`, requests go through Laravel's HTTP client: `Http::fake()` for `huispedia.dev/*` (or the
configured `HOUSEPEDIA_BASE_URL` host) and `Http::assertSent()` work as for any request. The facade also supports
Laravel's facade mocking (`Housepedia::shouldReceive('properties->withPostcode->valuation')`, which needs Mockery
1.6.13 or later, `Housepedia::spy()`); list methods return `Results`, so return `new Results([...])` from a mock.
