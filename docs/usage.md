# Usage

- [Facade](#facade)
- [Dependency injection](#dependency-injection)
- [Collections](#collections)
- [Immutable builders](#immutable-builders)
- [Raw requests](#raw-requests)
- [Exceptions](#exceptions)

<a name="facade"></a>
## Facade

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;

$valuation = Housepedia::properties()
    ->withPostcode('1076LJ', 12, '2')
    ->valuation();

$valuation->valuation()->value();

$stats = Housepedia::areas()->withMunicipality('GM0363')->stats();

$homes = Housepedia::addresses()->reverse(52.3453, 4.8627);
```

<a name="dependency-injection"></a>
## Dependency injection

Type-hint `Henzeb\Housepedia\Data\Intelligence\Laravel\Client` to receive the same client the
facade uses:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Illuminate\Support\Collection;

readonly class AddressService
{
    public function __construct(
        private Client $client,
    ) {
    }

    public function suggestions(string $query): Collection
    {
        return $this->client->addresses()->suggest($query);
    }
}
```

The core client, `Henzeb\Housepedia\Data\Intelligence\Client`, is registered as well and uses
the same configuration. It returns the core `Results` instead of Collections. `core()` on the
Laravel client returns it.

<a name="collections"></a>
## Collections

Endpoints that return a list return `Results`: a Laravel
[Collection](https://laravel.com/docs/collections) (`Illuminate\Support\Collection`) of the core
response objects with the `meta()` of the answer. Every Collection method is available:

| Method                               | Returns                                   |
|--------------------------------------|-------------------------------------------|
| `addresses()->suggest()`             | `Results<AddressSuggestion>`              |
| `addresses()->reverse()`             | `ReverseResults`                          |
| `addresses()->...->findAll()`        | `Results<Address>`                        |
| `valuations()->scenarios()`          | `Results<ScenarioValuation>`              |
| `account()->usage()`                 | `Results<Usage>`                          |
| `account()->requests()`              | `Results<RequestLogEntry>`                |

```php
$suggestions = Housepedia::addresses()->suggest('1076LJ');

$suggestions
    ->filter(fn (AddressSuggestion $suggestion) => $suggestion->type() === 'address')
    ->map->label();

$suggestions->meta()?->requestId();
$suggestions->meta()?->rateLimit()?->remaining();
```

Every method and every field of the response objects is in the
[core reference](https://github.com/henzeb/housepedia-data-intelligence/blob/main/docs/reference/shared.md);
where it returns `Results` or `ReverseResults`, this package returns its own `Results` and
`ReverseResults` with the same methods.

`meta()` works as in the core SDK; see
[metadata](https://github.com/henzeb/housepedia-data-intelligence/blob/main/docs/getting-started.md#metadata).

`ReverseResults` also has the areas of the coordinate:

```php
$nearby = Housepedia::addresses()->reverse(52.3453, 4.8627);

$nearby->areas()?->municipality()?->name();
```

Collection methods such as `filter()` and `map()` return a new collection without `meta()`;
read it from the result of the call.

Every other endpoint returns the core response object. Lists inside those objects, such as
`Woz::values()` or `References::references()`, are plain arrays; wrap them with `collect()`
when needed.

`valuations()->scenarios()` accepts any iterable of `ScenarioRow`, including a Collection.

`account()->requests()` returns one page of the request log; `meta()?->nextCursor()` is the
cursor of the next page. `account()->lazy()->requests()`
returns every entry of every page as a Laravel
[LazyCollection](https://laravel.com/docs/collections#lazy-collections)
(`Illuminate\Support\LazyCollection<int, RequestLogEntry>`). It takes the
filters of `requests()`; `limit` is the number of entries per page. A page is requested only
when the collection reaches it, and every enumeration starts at the first page again:

```php
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\RequestLogEntry;

$spent = Housepedia::account()->lazy()->requests(from: now()->subWeek())
    ->sum(fn (RequestLogEntry $entry) => $entry->credits());

$latestErrors = Housepedia::account()->lazy()->requests(status: 500)->take(10)->all();
```

<a name="immutable-builders"></a>
## Immutable builders

The builders offer the same locator methods as the core SDK and are immutable in the same
way:

```php
$properties = Housepedia::properties();

$first = $properties->withPostcode('1076LJ', 12, '2');
$second = $properties->withPostcode('2511BT', 10);
```

<a name="raw-requests"></a>
## Raw requests

`get()` and `post()` are the low-level request methods: they send a request to the path
you give and return a `Response` with the decoded JSON body. Use them for endpoints that
have no typed method yet. The response has the readers of the core
[`Response`](https://github.com/henzeb/housepedia-data-intelligence/blob/main/docs/raw-requests.md),
plus `collect()`, which returns the body or the array at a key as a Collection:

```php
$response = Housepedia::get('/v5/properties/1076LJ-12-2/listing');

$response->collect('data');   // Collection
$response->integer('data.0.price');
```

<a name="exceptions"></a>
## Exceptions

The package throws the exceptions of the core SDK, such as `NotFoundException` and
`RateLimitException`. See the
[core error documentation](https://github.com/henzeb/housepedia-data-intelligence/blob/main/docs/errors.md).
