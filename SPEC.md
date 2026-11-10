# Housepedia Data Intelligence for Laravel — Specification

| Item       | Value                                                                                                          |
|------------|----------------------------------------------------------------------------------------------------------------|
| Package    | `henzeb/housepedia-data-intelligence-laravel`                                                                       |
| Namespace  | `Henzeb\Housepedia\Data\Intelligence\Laravel`                                                                       |
| Depends on | `henzeb/housepedia-data-intelligence`, specified in its own `SPEC.md`                                               |
| Status     | Automated tests passing (2026-11-11), manual test plan passed (2026-11-11) |
| Date       | 2026-11-10                                                                                                     |

- [1. Scope](#1-scope)
- [2. Service provider](#2-service-provider)
- [3. Configuration](#3-configuration)
- [4. Client and facade](#4-client-and-facade)
- [5. Resource wrappers](#5-resource-wrappers)
- [6. Collections](#6-collections)
- [7. Raw requests](#7-raw-requests)
- [8. Errors](#8-errors)
- [9. Platform and dependencies](#9-platform-and-dependencies)
- [10. Quality requirements](#10-quality-requirements)
- [11. Known limitations](#11-known-limitations)
- [12. Manual test plan](#12-manual-test-plan)
- [13. Automated test status](#13-automated-test-status)
- [14. Release process](#14-release-process)

## 1. Scope

1.1. The package integrates the core SDK into Laravel: configuration, container bindings, a
facade and Laravel Collections for list endpoints.

1.2. It uses the core SDK through composition. HTTP transport, authentication, endpoint
paths, locator formatting, DTO hydration and error mapping exist only in the core SDK. The
test fake (4.7) recognises the endpoints by method and path to answer them.

1.3. It returns the core DTO classes and throws the core exception classes.

1.4. The repository is independent: its own Git history, Composer configuration, CI,
documentation and release tags.

1.5. The package ships Laravel Boost AI guidelines in
`resources/boost/guidelines/core.blade.php`: configuration, facade and injection, the
immutable builders, Collections, the low-level `get()`/`post()`, credit costs, exceptions
and testing with `Housepedia::fake()`, `Http::fake()` and facade mocking. They are part of the Composer package.

## 2. Service provider

2.1. `HousepediaServiceProvider` is registered through package auto-discovery
(`extra.laravel.providers`); the alias `Housepedia` is registered through
`extra.laravel.aliases`.

2.2. It merges `config/housepedia.php` under the `housepedia` key and publishes it with the
tag `housepedia-config`.

2.3. It registers these singletons:

| Binding                                         | Instance                                                        |
|-------------------------------------------------|-----------------------------------------------------------------|
| `HousepediaServiceProvider::HTTP_CLIENT` (`housepedia.http-client`) | `HttpClientAdapter` over Laravel's HTTP client, with `timeout` and `connect_timeout` from the configuration |
| `Henzeb\Housepedia\Data\Intelligence\Transport\Transport` | core transport with `api_key`, the HTTP client, Guzzle PSR-17 factories and a core `Config` built from `base_url`, `language`, `retry.retries` and `retry.service_unavailable` |
| `Henzeb\Housepedia\Data\Intelligence\Client`         | core client with the registered transport                       |
| `Henzeb\Housepedia\Data\Intelligence\Laravel\Client` | Laravel client wrapping the registered core client              |

2.4. Any PSR-18 client bound to `HousepediaServiceProvider::HTTP_CLIENT` before the
transport is resolved replaces `HttpClientAdapter`.

2.5. `HttpClientAdapter` implements PSR-18 `ClientInterface` and sends every request
through `Illuminate\Http\Client\Factory`. `Http::fake()`, `Http::preventStrayRequests()`,
`Http::assertSent()`, global `Http` middleware and the `RequestSending`, `ResponseReceived` and
`ConnectionFailed` events (used by Telescope and Pulse) therefore apply to the API requests.

2.6. For each request, `HttpClientAdapter`:

- sends the method, the URI including its query and every header of the PSR-7 request, and
  the body with its `Content-Type` when the body is not empty;
- uses `timeout` and `connect_timeout` from the configuration (3.4);
- does not retry and does not throw on a 4xx or 5xx status; it returns the PSR-7 response
  of Laravel's response (`toPsrResponse()`), and the core maps the status, retries and caches
  as specified in the core specification;
- turns an `Illuminate\Http\Client\ConnectionException` into `NetworkException`, which
  implements PSR-18 `NetworkExceptionInterface` and returns the sent request from
  `getRequest()`. The core turns it into its `TransportException`. Every other exception, such
  as the one `Http::preventStrayRequests()` throws, passes through unchanged.

2.7. An answer from the cache sends no request, so it does not reach `Http::fake()` or the
`Http` events.

## 3. Configuration

3.1. `config/housepedia.php`:

| Key               | Environment variable          | Default                 |
|-------------------|-------------------------------|-------------------------|
| `api_key`                   | `HOUSEPEDIA_API_KEY`                    | `null`  |
| `base_url`                  | `HOUSEPEDIA_BASE_URL`                   | `null`  |
| `version`                   | `HOUSEPEDIA_VERSION`                    | `'v5'`  |
| `language`                  | `HOUSEPEDIA_LANGUAGE`                   | `null`  |
| `timeout`                   | `HOUSEPEDIA_TIMEOUT`                    | `30`    |
| `connect_timeout`           | `HOUSEPEDIA_CONNECT_TIMEOUT`            | `10`    |
| `retry.retries`             | `HOUSEPEDIA_RETRIES`                    | `2`     |
| `retry.service_unavailable` | `HOUSEPEDIA_RETRY_SERVICE_UNAVAILABLE`  | `false` |
| `cache.enabled`             | `HOUSEPEDIA_CACHE`                      | `false` |
| `cache.store`               | `HOUSEPEDIA_CACHE_STORE`                | `null`  |
| `cache.prefix`              | `HOUSEPEDIA_CACHE_PREFIX`               | `housepedia-data-intelligence` |
| `cache.max_age_in_seconds`  | `HOUSEPEDIA_CACHE_MAX_AGE`              | `null`  |

3.2. An empty or non-string `api_key` is passed as `null`: authenticated calls throw the core
`MissingApiKeyException`, `catalog()` works.

3.3. An empty or non-string `base_url` or `language` is passed as `null`; the core then uses
`https://huispedia.dev` and sends no `Accept-Language`.

3.4. Numeric timeout values (including numeric strings) are used in seconds; other values
fall back to the defaults.

3.4a. A numeric `retry.retries` (including numeric strings) is the number of retries; `0`
disables retrying; other values fall back to `2`. `retry.service_unavailable` is read as a
boolean (`true`, `1`, `"true"`, `"1"`, `"on"`, `"yes"`), otherwise `false`.

3.4b. On boot the provider sets `Client::retryUsing()` of the core to a callback that uses
Laravel's `retry()` helper with `retries() + 1` attempts, `retryDelay()` in milliseconds as the
wait and `shouldRetry()` as the condition. The wait goes through `Illuminate\Support\Sleep`,
so `Sleep::fake()` removes it in tests.

3.4c. When `cache.enabled` is true (read as a boolean like `retry.service_unavailable`), the
core `Config` gets the Laravel cache store named by `cache.store` as its PSR-16 cache; an empty
or non-string store is the default store. A store selects its own connection, such as a Redis
connection, in `config/cache.php`. An empty or non-string `cache.prefix` falls back to
`housepedia-data-intelligence`. A numeric `cache.max_age_in_seconds` (including numeric strings) is
`maxCacheAgeInSeconds`, at least `0`; other values are `null`, which follows the API. When
`cache.enabled` is false, no cache is passed. Caching itself is specified in the core
specification (4.13).

3.4d. `version` is passed to the core `Config` as the API version: an integer as it is, a
non-empty string as it is, anything else as `null`, which uses the core's fallback version.
The core normalises it (`5`, `'5'`, `'v5'` and `'V5'` are the same) and throws
`UnsupportedVersionException` for a version it does not support, when the client is resolved.

3.5. Credentials are only read from configuration.

## 4. Client and facade

4.1. `Henzeb\Housepedia\Data\Intelligence\Laravel\Client` exposes `addresses()`, `properties()`,
`buildings()`, `areas()`, `valuations()`, `account()`, `catalog()`, `get()`, `post()`,
`version()`, `withVersion()`, `withMaxCacheAge()`, `withoutCache()` and `core()`.
`withVersion()`, `withMaxCacheAge()` and `withoutCache()` return a new Laravel client around
the core client's copy; `version()` returns the API version of the core client.

4.2. The resource methods return the Laravel resource wrappers of
[section 5](#5-resource-wrappers); `catalog()` returns the core `Contracts\DTO\Catalog\Catalog`; `core()` returns
the core client it wraps.

4.3. `Facades\Housepedia` resolves `Henzeb\Housepedia\Data\Intelligence\Laravel\Client` and
declares every client method with `@method static` for autocompletion.

4.4. The Laravel client and the core client are both available through dependency
injection and share the same transport.

4.5. The facade can be mocked with Laravel's facade mocking: `Housepedia::shouldReceive()`,
including Mockery chains such as `shouldReceive('properties->withPostcode->valuation')`,
and `Housepedia::spy()`. A partial mock of the resolved client is set with
`Housepedia::swap(Mockery::mock(app(Client::class)))`. The resource wrappers, the client, the
response and the response objects can be mocked with `Mockery::mock()`. `docs/testing.md` and the Boost guideline
describe it.

4.6. The API requests can be faked with `Http::fake()` for the URL pattern `huispedia.dev/*`
(or the configured `base_url`), returning `Http::response()` with a JSON body, and checked
with `Http::assertSent()`. `docs/testing.md` and the Boost guideline describe it next to binding
a PSR-18 mock client (2.4).

4.7. `Housepedia::fake(array $responses = [])` binds a `Testing\HousepediaFake` to
`HousepediaServiceProvider::HTTP_CLIENT`, forgets the resolved transport, core client and
Laravel client, and returns the fake. Every call through the facade, an injected client or the
core client resolved afterwards reaches the fake instead of the network. `$responses` maps the
value of a `Testing\Endpoint` case to a response (4.9); an unknown key throws a `ValueError`.

4.8. `Testing\Endpoint` is a string-backed enum with a case per endpoint the SDK calls. The
value is the product id of the endpoint in the Housepedia catalog (`valuation`,
`property_woz`, `address_suggest`, …). The fake recognises an endpoint by the HTTP method and
the end of the request path (`/v5/properties/{locator}/valuation`); fixed paths
(`/v5/addresses/suggest`, `/v5/areas/lookup`) take precedence over a locator. `HEAD` on the
transactions path (`hasTransactions()`) is `Endpoint::Transactions`. A request that matches
no endpoint throws `Testing\StrayRequestException` and is not recorded.

4.9. Without a response, an endpoint answers 200 with its example response from
`resources/fixtures/v5` (an example answer per endpoint, part of the Composer package);
`Endpoint::Address` with the `all` query parameter (`findAll()`) answers the example list.
A response is one of:
- an array, merged into the example response: associative arrays are merged recursively,
  lists and scalar values replace the example value;
- `Testing\FakeResponse::json(array $body, int $status = 200, array $headers = [])`, the
  body as given;
- `Testing\FakeResponse::error(int $status, ?string $code = null, ?string $detail = null,
  array $body = [], array $headers = [])`, a problem body (`status`, `title`, `code`,
  `detail` and `$body`) that the core turns into the matching exception (`404` →
  `NotFoundException`, `409` with code `address_ambiguous` → `AddressAmbiguousException`, …);
- a `Closure` that receives the `Testing\RecordedRequest` and returns one of the above.

A `HEAD` request answers the status of the response without a body.

4.10. `HousepediaFake::respond(Endpoint $endpoint, array|FakeResponse|Closure ...$responses)`
sets the responses of an endpoint and returns the fake. Several responses answer the calls in
order; the last one answers every call after it.

4.11. Every request that reaches the fake is recorded as a `Testing\RecordedRequest` with
`endpoint()`, `method()`, `url()`, `path()`, `query(?string $key = null)`, `body()`,
`header(string $name)` and `toPsrRequest()`. `HousepediaFake::recorded(?Endpoint $endpoint =
null, ?Closure $callback = null)` returns them as a `Collection`.

4.12. `HousepediaFake` and the facade assert with PHPUnit assertions:
`assertSent(Endpoint $endpoint, ?Closure $callback = null)`, `assertNotSent(Endpoint $endpoint,
?Closure $callback = null)`, `assertSentCount(int $count, ?Endpoint $endpoint = null)` and
`assertNothingSent()`. The callback receives the `RecordedRequest` and returns a boolean. The
facade also offers `recorded()`. The facade assertions throw a `RuntimeException` when
`Housepedia::fake()` was not called.

4.13. Requests reaching the fake do not pass through Laravel's HTTP client: `Http::fake()`,
`Http::preventStrayRequests()` and the `Http` events do not see them. Retries, caching and
error mapping of the core still apply.

## 5. Resource wrappers

5.1. `Resources\Addresses`, `Properties`, `Buildings`, `Areas`, `PostcodeArea`, `Valuations`,
`Account` and `LazyAccount` wrap the core resource of the same name and implement its core
interface in `Contracts`, so they work for every API version the core supports. `Areas::withPostcode4()` and
`withPostcode6()` return the `PostcodeArea` wrapper; `Account::lazy()` returns the
`LazyAccount` wrapper.

5.2. Each wrapper declares every public method of its core interface with the same name,
parameter names, parameter types and defaults. Forwarding is explicit; there is no
`__call()`.

5.3. Locator methods (`with*()`, `national()`) return a new wrapper around the new core
resource; the wrapper they are called on is unchanged.

5.3a. `withMaxCacheAge()` and `withoutCache()` return a new wrapper around the core
resource's copy, like the locator methods.

5.4. Every method sends exactly the request of the core method and sends it once.

5.5. `Valuations::scenarios()` accepts `iterable<ScenarioRow>`, including a Collection.

## 6. Collections

6.1. Methods that return the core `Contracts\DTO\Results<T>` return
`Henzeb\Housepedia\Data\Intelligence\Laravel\Results<T>`, which extends
`Illuminate\Support\Collection<int, T>` with keys `0…n-1`, adds `meta(): ?Meta` and
`data(): list<T>` and implements `Contracts\DTO\Results`. The core `Contracts\DTO\ReverseResults`
becomes `Laravel\ReverseResults`, which extends `Results<ReverseAddress>`, adds
`areas(): ?AreaHierarchy` and implements `Contracts\DTO\ReverseResults`. A core `array` return
would become `Collection<int, T>`:

| Method                         | Returns                                |
|--------------------------------|----------------------------------------|
| `addresses()->suggest()`       | `Results<AddressSuggestion>`           |
| `addresses()->reverse()`       | `ReverseResults`                       |
| `addresses()->…->findAll()`    | `Results<Address>`                     |
| `valuations()->scenarios()`    | `Results<ScenarioValuation>`           |
| `account()->usage()`           | `Results<Usage>`                       |
| `account()->requests()`        | `Results<RequestLogEntry>`             |

6.2. Collection items are the core DTO instances; `meta()` and `areas()` are the core objects
of the answer, including `Meta::rateLimit()`.

6.2a. Collection methods that return a new collection (`filter()`, `map()`, …) create it
without `meta()` and `areas()`; they are read from the result of the call.

6.3. Every other method returns the core return type unchanged; lists inside DTOs stay
arrays.

6.4. Methods that return an `iterable` in the core interface (a `Generator` in `V5`) return
`Illuminate\Support\LazyCollection` over it:

| Method                             | Returns                                     |
|------------------------------------|---------------------------------------------|
| `account()->lazy()->requests()`    | `LazyCollection<int, RequestLogEntry>`      |

No request is sent before the collection is enumerated. Every enumeration calls the core
method again, so it starts at the first page and sends the requests again, except for pages
answered from the cache.

## 7. Raw requests

7.1. `get()` and `post()` are the low-level request methods of the core. They take the
arguments of the core methods and return
`Henzeb\Housepedia\Data\Intelligence\Laravel\Response`.

7.2. `Response` extends the core `Response` and adds
`collect(?string $key = null): Collection<array-key, mixed>`: the whole body without a key,
the array at the dot-notation key, or an empty Collection when the key is missing.

## 8. Errors

8.1. The package throws the core exceptions unchanged. Its own exception classes are the
PSR-18 `NetworkException` of `HttpClientAdapter` and `Testing\StrayRequestException` of the
test fake (4.8).

## 9. Platform and dependencies

9.1. PHP `^8.3`, including PHP 8.6; Laravel 12 and 13. No runtime or development dependency
may block installation on a supported PHP version. CI covers PHP 8.3, 8.4, 8.5 and 8.6, each
with the lowest and the latest dependencies; Composer picks the Laravel version. On PHP 8.6
the test tools (`pestphp/pest-plugin-laravel` 5) require Laravel 13.23 or later. The package
itself installs with Laravel 12 on PHP 8.6. The 8.6 jobs run with `continue-on-error` until PHP 8.6 is
released (planned 19 November 2026). CI installs with Composer's `notify-on-install` disabled, so
CI runs do not count as Packagist downloads.

9.2. Laravel 11 is not supported: it is past its security support and Composer blocks every
Laravel 11 release because of open security advisories.

9.3. Runtime dependencies: `henzeb/housepedia-data-intelligence ^1.0` (from Packagist),
`illuminate/collections`, `illuminate/contracts`, `illuminate/http` and `illuminate/support`
`^12.0|^13.0`, `guzzlehttp/guzzle ^7.8|^8.0`,
`psr/http-client ^1.0`.

9.3a. Guzzle 7 and 8 are both supported, so the package installs next to the Guzzle version
a Laravel application already has. Laravel 12 uses Guzzle 7; Laravel 13 allows both. CI runs
every Laravel 13 combination with Guzzle 7 and with Guzzle 8.

9.4. Development dependencies: `mockery/mockery ^1.6.13` (chains through methods returning
`static` need 1.6.13), `orchestra/testbench ^10.0|^11.0`, `pestphp/pest ^4.0|^5.0`,
`pestphp/pest-plugin-laravel ^4.0|^5.0`, `php-http/mock-client ^1.6.1`, `phpstan/phpstan ^2.1`,
`laravel/pint ^1.30`.

9.5. Installing this package installs a compatible core SDK through Composer.

## 10. Quality requirements

10.1. PHPStan at level `max` with bleeding edge reports no errors on `src` and `config`.

10.2. Collections carry PHPDoc generics (`Collection<int, AddressSuggestion>`).

10.3. Classes and properties are not `final` and not `readonly`, so every class can be extended
and mocked. Properties are private and have no setters, which makes the objects immutable.
Source files carry no explanatory comments. An architecture test enforces this for
`src`.

10.4. The Pest suite (Testbench) covers provider registration, configuration, publishing,
facade and alias resolution, dependency injection, forwarding of every endpoint, `Results`
return types, item types and `meta()`, immutability, exception propagation, one request per
call, the API version from configuration and `withVersion()`, and method parity with the
core interfaces (reflection; every wrapper and both results implement their core interface, a
core `Results` return becomes the Laravel `Results`, a core `ReverseResults` return the Laravel
`ReverseResults` with the same getters, a core `array` return `Collection` and a core
`iterable` return `LazyCollection`), and `HttpClientAdapter`: the method, URI,
headers and body that reach `Http::fake()`, the configured timeouts, the `RequestSending` and
`ResponseReceived` events, a 4xx or 5xx answer reaching the core as its exception, a
`ConnectionException` becoming the core `TransportException` and a stray request passing
through, and the test fake: an `Endpoint` case per endpoint the core calls, each matching its
path and answering its example response, the merging of array responses, `FakeResponse`,
sequences, closures, `HEAD`, stray requests and every assertion passing and failing. Tests use
mocked HTTP responses only.

10.5. CI (`.github/workflows/tests.yml`) runs `composer validate --strict`, installs with
`prefer-lowest` and `prefer-stable` per PHP version, runs PHPStan
and Pest.

10.6. Laravel Pint (`laravel/pint`, a development dependency) checks the code style of `src`,
`config` and `tests` with the `psr12` preset from `pint.json`. `composer format` fixes the
style and `composer format-check` reports files that differ. A `style` job in
`.github/workflows/tests.yml` runs `vendor/bin/pint --test` once on PHP 8.4 with Laravel 13
and `prefer-stable`. `pint.json` is not part of the Composer package.

10.7. `SECURITY.md` says which versions get security fixes (the latest minor release of the
current major version) and asks to report a vulnerability by email to
henzeberkheij@gmail.com instead of in a public issue, with the affected version, the steps to
reproduce it and its impact. It is part of the Composer package; the README links to it.

## 11. Known limitations

- `account()->requests()` returns one page; every entry of every page is
  `account()->lazy()->requests()`. Lists nested in single results (`Woz::values()`,
  `References::references()`, …) are arrays.
- The HTTP client is created once per application; changing `housepedia.*` configuration
  after the client was resolved has no effect until the container is rebuilt.
- Octane: the clients are singletons without request state, so they are safe to share; this
  has not been tested under Octane.

## 12. Manual test plan

Run in a fresh Laravel 12 application and a fresh Laravel 13 application. The core checklist
covers the endpoints; keep this run to cheap endpoints (about 5 credits per application).

### 1. Installation

- [ ] `composer require henzeb/housepedia-data-intelligence-laravel` installs the core package as
      a dependency.
- [ ] `php artisan package:discover` lists `henzeb/housepedia-data-intelligence-laravel`.
- [ ] `php artisan vendor:publish --tag=housepedia-config` creates `config/housepedia.php`.

### 2. Configuration

- [ ] With `HOUSEPEDIA_API_KEY` set, `Housepedia::account()->details()` returns the account.
- [ ] Without `HOUSEPEDIA_API_KEY`, the same call throws `MissingApiKeyException`.
- [ ] With `HOUSEPEDIA_RETRIES=0`, a burst of more calls than
      `Housepedia::account()->details()->meta()?->rateLimit()?->limit()` throws `RateLimitException`;
      with the default, the burst succeeds.
- [ ] `HOUSEPEDIA_LANGUAGE=nl`: an error `detail()` is in Dutch.
- [ ] `HOUSEPEDIA_TIMEOUT=0.001` makes calls fail with `TransportException`.
- [ ] `php artisan config:cache` followed by a call still uses the configured key.
- [ ] With `HOUSEPEDIA_CACHE=true`, a second
      `Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find()` sends no request and
      the key is in the default store; with `HOUSEPEDIA_CACHE_STORE` set to a store on a
      separate Redis connection, the key is in that Redis database.

### 3. Facade and dependency injection

- [ ] `Housepedia::addresses()->suggest('1076LJ 12-2')` returns `Results` of
      `AddressSuggestion` with street and city, and a `meta()` with `rateLimit()` (free).
- [ ] A controller or job with `Henzeb\Housepedia\Data\Intelligence\Laravel\Client` in its
      constructor receives the client and returns the same result.
- [ ] `app(Henzeb\Housepedia\Data\Intelligence\Client::class)` returns the core `Results` for the
      same call.
- [ ] In Tinker: `Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find()` (1 credit).

### 4. Collections

- [ ] `Housepedia::addresses()->reverse(52.34536, 4.86274)->map->street()` and its `areas()`
      (1 credit).
- [ ] `Housepedia::addresses()->withPostcode('1076LJ', 12)->findAll()->count()` (1 credit).
- [ ] `Housepedia::account()->usage()->sum(fn ($usage) => $usage->credits())` (free).

### 5. Errors and raw requests

- [ ] A non-existent address throws `Henzeb\Housepedia\Data\Intelligence\Exceptions\NotFoundException`
      and is reported by the Laravel exception handler with its message.
- [ ] `Housepedia::get('/v5/account')->collect('entitlements')` returns a Collection.
- [ ] `Housepedia::catalog()` works without an API key configured.

### 6. Testing support

- [ ] `Housepedia::fake()` in a feature test answers `Housepedia::properties()->withPostcode(…)->valuation()`
      with the example response, without a request to `huispedia.dev`, and
      `Housepedia::assertSent(Endpoint::Valuation)` passes.
- [ ] `Housepedia::fake([Endpoint::Valuation->value => FakeResponse::error(404)])` makes the call
      throw `NotFoundException`.
- [ ] `Http::fake()` with a response for `huispedia.dev/*` in a feature test (see
      `docs/testing.md`) intercepts the calls, and `Http::assertSent()` sees them.
- [ ] `Http::preventStrayRequests()` without a fake for `huispedia.dev/*` stops a call.
- [ ] Binding `Http\Mock\Client` to `HousepediaServiceProvider::HTTP_CLIENT` in a feature test
      (see `docs/testing.md`) intercepts the calls.
- [ ] Telescope's HTTP client watcher shows a call, with the `Authorization` header hidden.

## 13. Automated test status

234 tests, 1039 assertions, 100% coverage, PHPStan level max without errors. Verified locally
with Laravel 13 (prefer-stable) and Laravel 12 (prefer-lowest) on PHP 8.4, and with
Laravel 13.40 on PHP 8.6.0RC4 in a container (Pest 5.3.5, pest-plugin-laravel 5.0.1, PHPStan
without errors, all tests passing with `failOnDeprecation`). Verified with Guzzle 8 on
PHP 8.5 with Laravel 13 (Guzzle 8.0.1 prefer-lowest, 8.3.1 prefer-stable). All tests use
mocked HTTP responses.

On 2026-11-09 the caching items of the manual test plan passed in fresh Laravel 12.71 and
Laravel 13 applications: with `HOUSEPEDIA_CACHE=true` two `find()` calls cost 1 credit and the key is in
the default (database) store; with `HOUSEPEDIA_CACHE_STORE` set to a Redis store on its own
connection (database 3), the key is in that Redis database.

On 2026-11-10 the Telescope item of the manual test plan passed in a fresh Laravel 13.40
application with Telescope 5.27: `account()->details()` is recorded as a client request
(`GET https://huispedia.dev/v5/account`, 200) with the `Authorization` header shown as
`********`.

On 2026-11-11 the whole manual test plan passed in fresh Laravel 12.72 and Laravel 13.42
applications on PHP 8.4, with both packages installed from path repositories: installation,
discovery and publishing; every configuration item (missing key, `HOUSEPEDIA_RETRIES=0`
limited after about 68 calls and the default succeeding, Dutch errors, a timeout, `config:cache`,
caching in the database store and in a Redis store on its own connection, database 3); the
facade, a controller with the client injected, the core client and Tinker; `reverse()` with
`areas()`, `findAll()->count()` and `usage()->sum()`; the exception handler, `collect()` and
the catalog without a key; and `Http::fake()`, `Http::preventStrayRequests()` and a bound
`php-http/mock-client` in a feature test. Telescope 5.28 (Laravel 13.42) records the call with
the `Authorization` header shown as `********`. About 10 credits.

## 14. Release process

14.1. A release follows these steps:

1. Push the changes to `main` of `github.com/henzeb/housepedia-data-intelligence-laravel`.
2. CI passes for every PHP, Laravel and Guzzle combination of 9.1 and 9.3a.
3. Run the [manual test plan](#12-manual-test-plan), or the parts the change affects.
4. Rename `[Unreleased]` in `CHANGELOG.md` to the version and date, and update the compare
   links.
5. Tag the release with a signed `vX.Y.Z` tag following semantic versioning; Packagist
   picks the tag up from GitHub.
6. Verify `composer require henzeb/housepedia-data-intelligence-laravel` in a fresh Laravel 12
   and Laravel 13 application.

14.2. A new major version of the core needs a new constraint for
`henzeb/housepedia-data-intelligence` in `composer.json` and in the CI `core` axis, released as a
new version of this package.

