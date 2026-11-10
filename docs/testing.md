# Testing your application

- [Faking Housepedia](#faking-housepedia)
- [Mocking the facade](#mocking-the-facade)
- [Faking HTTP responses](#faking-http-responses)
- [Binding a PSR-18 client](#binding-a-psr-18-client)

<a name="faking-housepedia"></a>
## Faking Housepedia

`Housepedia::fake()` answers every call with an example response of its endpoint, without a
request to the API. It applies to the facade and to injected clients:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\Endpoint;

Housepedia::fake();

$valuation = Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation();

Housepedia::assertSent(Endpoint::Valuation);
```

`Endpoint` has a case per endpoint; its value is the product id of the endpoint in
`Housepedia::catalog()`, such as `valuation` or `property_woz`.

### Responses

Pass responses per endpoint to `fake()`, keyed by the value of the case, or set them with
`respond()` on the fake it returns. An array is merged into the example response, so it only
needs the fields the test is about. Lists replace the example list:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\FakeResponse;

$fake = Housepedia::fake([
    Endpoint::Valuation->value => ['valuation' => ['value' => 450000]],
    Endpoint::AddressSuggest->value => ['data' => [['type' => 'address', 'label' => 'Amazonenstraat 12-2, 1076LJ Amsterdam']]],
]);

$fake->respond(Endpoint::PropertyWoz, FakeResponse::error(404));
```

| Response                                                   | Answer                                              |
|------------------------------------------------------------|-----------------------------------------------------|
| An array                                                   | The example response with the array merged into it |
| `FakeResponse::json($body, $status, $headers)`             | The body as given                                   |
| `FakeResponse::error($status, $code, $detail, $body, $headers)` | An error, thrown as the matching exception    |
| A closure                                                  | What the closure returns for the `RecordedRequest`  |

`FakeResponse::error(404)` throws a `NotFoundException`, `FakeResponse::error(409,
'address_ambiguous', body: ['errors' => [$address]])` an `AddressAmbiguousException` with that
candidate, and `FakeResponse::error(429)` a `RateLimitException` after the retries; call
`Sleep::fake()` to skip the wait. A closure answers per request:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\RecordedRequest;

Housepedia::fake([
    Endpoint::AddressSuggest->value => fn (RecordedRequest $request) => [
        'data' => [['type' => 'address', 'label' => $request->query('q')]],
    ],
]);
```

Several responses for one endpoint answer the calls in order; the last one answers every call
after it:

```php
Housepedia::fake()->respond(
    Endpoint::Valuation,
    FakeResponse::error(503),
    ['valuation' => ['value' => 450000]],
);
```

A request to a path without an `Endpoint` case, such as `Housepedia::get('/v5/unknown')`,
throws a `StrayRequestException`.

### Assertions

```php
Housepedia::assertSent(Endpoint::Valuation);
Housepedia::assertSent(Endpoint::Valuation, fn (RecordedRequest $request) => str_contains($request->path(), '1076LJ-12-2'));
Housepedia::assertNotSent(Endpoint::PropertyWoz);
Housepedia::assertSentCount(1);
Housepedia::assertSentCount(1, Endpoint::Valuation);
Housepedia::assertNothingSent();

Housepedia::recorded(Endpoint::AddressSuggest)->first()?->query('q');
```

A `RecordedRequest` has `endpoint()`, `method()`, `url()`, `path()`, `query()`, `body()`,
`header()` and `toPsrRequest()`. The same assertions and `recorded()` are available on the fake
that `fake()` returns.

The fake replaces Laravel's HTTP client for Housepedia, so `Http::fake()` and the `Http` events
do not see these requests. Caching and retries still apply.

<a name="mocking-the-facade"></a>
## Mocking the facade

The `Housepedia` facade supports Laravel's facade mocking. Mock a call chain, including the
locator, and return a mocked response object:

```php
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Estimate;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Valuation;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;

$estimate = Mockery::mock(Estimate::class);
$estimate->shouldReceive('value')->andReturn(450000);

$valuation = Mockery::mock(Valuation::class);
$valuation->shouldReceive('valuation')->andReturn($estimate);

Housepedia::shouldReceive('properties->withPostcode->valuation')->andReturn($valuation);
```

Chains through a locator need Mockery 1.6.13 or later.

To check the arguments, mock the resource:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Properties;

$properties = Mockery::mock(Properties::class);
$properties->shouldReceive('withPostcode')->once()->with('1076LJ', 12, '2')->andReturnSelf();
$properties->shouldReceive('valuation')->once()->andReturn($valuation);

Housepedia::shouldReceive('properties')->andReturn($properties);
```

List methods return `Results`, a Laravel `Collection` with `meta()`:

```php
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\AddressSuggestion;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Addresses;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;

$suggestion = Mockery::mock(AddressSuggestion::class);
$suggestion->shouldReceive('label')->andReturn('Amazonenstraat 12-2, 1076LJ Amsterdam');

$addresses = Mockery::mock(Addresses::class);
$addresses->shouldReceive('suggest')->with('1076LJ 12-2')->andReturn(new Results([$suggestion]));

Housepedia::shouldReceive('addresses')->andReturn($addresses);
```

`Housepedia::spy()` records the calls for assertions afterwards:

```php
$spy = Housepedia::spy();

// code under test

$spy->shouldHaveReceived('properties')->once();
```

To mock some methods and let the others call the API, swap in a partial mock of the client:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;

$client = Mockery::mock(app(Client::class));
$client->shouldReceive('get')->with('/v5/account')->andReturn($response);

Housepedia::swap($client);
```

Errors are thrown by returning an exception from the mock:

```php
use Henzeb\Housepedia\Data\Intelligence\Exceptions\NotFoundException;

$properties->shouldReceive('valuation')->andThrow(new NotFoundException(404, 'not_found', 'Not Found'));
```

<a name="faking-http-responses"></a>
## Faking HTTP responses

The requests go through Laravel's HTTP client. To run the real resources and response
objects, fake the answers with `Http::fake()` and check the requests with `Http::assertSent()`:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

Http::fake([
    'huispedia.dev/*' => Http::response(['data' => [
        ['type' => 'address', 'label' => 'Amazonenstraat 12-2, 1076LJ Amsterdam', 'street' => 'Amazonenstraat', 'city' => 'Amsterdam'],
    ]]),
]);

$suggestions = Housepedia::addresses()->suggest('1076LJ 12-2');

Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://huispedia.dev/v5/addresses/suggest'));
```

With a `base_url` in the configuration, fake that host instead. `Http::preventStrayRequests()`
also stops Housepedia requests without a fake. An error status is thrown as the matching
exception:

```php
Http::fake(['huispedia.dev/*' => Http::response(['title' => 'Not found', 'status' => 404, 'code' => 'not_found'], 404)]);

Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find(); // NotFoundException
```

<a name="binding-a-psr-18-client"></a>
## Binding a PSR-18 client

Any PSR-18 client bound to `HousepediaServiceProvider::HTTP_CLIENT` replaces Laravel's HTTP
client, for example [php-http/mock-client](https://github.com/php-http/mock-client):

```php
use GuzzleHttp\Psr7\Response;
use Henzeb\Housepedia\Data\Intelligence\Laravel\HousepediaServiceProvider;
use Http\Mock\Client;

$http = new Client();
$http->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'data' => [['type' => 'address', 'label' => 'Amazonenstraat 12-2, 1076LJ Amsterdam', 'street' => 'Amazonenstraat', 'city' => 'Amsterdam']],
])));

$this->app->instance(HousepediaServiceProvider::HTTP_CLIENT, $http);

$suggestions = Housepedia::addresses()->suggest('1076LJ 12-2');

$http->getLastRequest()->getUri()->getPath(); // /v5/addresses/suggest
```

Bind the client before the Housepedia client is first resolved.
