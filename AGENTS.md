# AGENTS.md

Guidance for coding agents working in `housepedia-data-intelligence-laravel`.

## Project

`henzeb/housepedia-data-intelligence-laravel` — Laravel 12/13 integration for the core SDK
`henzeb/housepedia-data-intelligence` (sibling repository `../housepedia-data-intelligence`). Branded **Housepedia**
everywhere; "huispedia" only in API URLs and Composer keywords.

## Spec-driven

`SPEC.md` is the specification for this package (requirements, limitations, manual test plan); the core's
`SPEC.md` covers endpoints and pricing. Change `SPEC.md` first, then tests, then code. A change to an endpoint
starts in the core repository.

## Commands

```bash
composer test                                   # vendor/bin/pest
composer analyse                                # vendor/bin/phpstan analyse (level max; src + config)
composer format                                 # vendor/bin/pint (psr12)
composer format-check                           # vendor/bin/pint --test
vendor/bin/pest tests/Integration/ParityTest.php
vendor/bin/pest --filter='forwards every endpoint'
```

### Installing locally

`composer.json` requires the core as `^1.0` from Packagist. To develop against the sibling checkout
`../housepedia-data-intelligence` instead, install with:

```bash
php bin/install-local                  # composer update
php bin/install-local require foo/bar  # any other composer command
```

The script writes `composer.local.json` (a copy of `composer.json` with the repositories replaced by a symlinked
path repository and the core constraint allowing its `1.x-dev` version) and runs `composer` with `COMPOSER=composer.local.json`, which writes `composer.local.lock`.
Both files are gitignored; `composer.json` is never modified. It works with a local PHP and with lerd, as long as
`composer` is on the `PATH` where `php` runs.

The core is symlinked into `vendor/`, so core changes are picked up without reinstalling.

## Architecture

- Thin adapter: all HTTP, auth, paths, locator formatting, DTO hydration and error mapping live in the core.
  This package adds config, container bindings, the facade, Collections and a test fake. It defines no DTOs, and no
  exceptions except the PSR-18 `NetworkException` of `HttpClientAdapter` and `Testing\StrayRequestException`. `Results` and `ReverseResults` are Collections that carry the core
  `Meta` (and `AreaHierarchy`) of a list answer.
- `HousepediaServiceProvider` binds singletons in a chain: `HTTP_CLIENT` (`housepedia.http-client`,
  `HttpClientAdapter`: a PSR-18 client over Laravel's `Http` factory with config timeouts, so `Http::fake()` and
  the `Http` events apply) → core `Transport` → core `Client` → Laravel `Client`. Tests swap the HTTP client by binding a
  mock to `HTTP_CLIENT` in `tests/TestCase.php` before anything resolves. Config values are narrowed by hand
  because `Illuminate\Contracts\Config\Repository` has no typed getters.
- The core is versioned (`V5\` classes behind `Contracts\` interfaces); this package only talks to the interfaces.
  `Resources\*` wrap the core interface of the same name (`Contracts\Addresses as CoreAddresses`) and implement
  it, with explicit, fully typed forwarding (no `__call`). `Client::withVersion()` and `config('housepedia.version')`
  (`HOUSEPEDIA_VERSION`) choose the API version.
  Locator methods return `new static($this->resource->with…())`; `withMaxCacheAge()`/`withoutCache()` come from
  `Concerns\ConfiguresCache` the same way. The provider passes a Laravel cache store as the core's PSR-16 cache
  when `cache.enabled` is true. Methods whose core return type is `DTO\Results` return the Laravel `Results`
  (`new Results($results->data(), $results->meta())`), core `DTO\ReverseResults` becomes `ReverseResults`, a core
  `array` would become `Collection<int, T>`, methods returning an `iterable` (the core `Generator`) return a `LazyCollection`; everything
  else returns the core type unchanged.
- `ParityTest` uses reflection to require that every Laravel wrapper (and the Laravel `Client`, plus `core()`)
  implements and has the same public methods, parameter names, types and optionality as its core interface, that core
  `Results`/`ReverseResults` returns become the Laravel `Results`/`ReverseResults` (with the same getters), core
  `array` returns `Collection` and core `iterable` returns `LazyCollection`. Any new core method must be mirrored here or that test fails.
- `Response` extends the core `Response` and only adds `collect()`.
- `Testing\` holds the test fake: `Housepedia::fake()` binds a `HousepediaFake` (a PSR-18 client) to `HTTP_CLIENT`
  and forgets the resolved transport and clients. `Endpoint` (catalog product id as value) maps each endpoint to its
  method(s), path and fixture; a new core endpoint needs a case, which `FakeTest` checks against the catalog fixture.

## Tests

Pest + Testbench (`tests/TestCase.php` registers the provider, the `Housepedia` alias and an API key). Helpers in
`tests/Pest.php`: `respondWith('<fixture>')`, `jsonResponse()`, `jsonFixture()`, `lastRequest()`, `queryOf()`;
the mock client is `$this->http`. `resources/fixtures/v5` holds an example response per endpoint (copies of the core
fixtures with `meta.next_cursor` set to `null`); the tests and the test fake read them.

## Conventions

- No `final` or `readonly` classes or properties; properties are private without setters. No explanatory code
  comments.
- Laravel 11 is not supported (EOL, blocked by Composer security advisories); don't re-add it to constraints or
  the CI matrix.
