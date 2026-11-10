# Changelog

All notable changes to `henzeb/housepedia-data-intelligence-laravel` are documented in this file.
The project follows [semantic versioning](https://semver.org).

## [1.0.0] - 2026-11-10

First release.

- Laravel integration of `henzeb/housepedia-data-intelligence`: configuration, the `Housepedia`
  facade and dependency injection. See [usage](docs/usage.md).
- `HOUSEPEDIA_VERSION` and `Housepedia::withVersion()` pin the API version, `v5` by default. See
  [configuration](docs/configuration.md).
- List endpoints return `Results`, a Laravel `Collection` with the `meta()` of the answer (request id,
  credits, `rateLimit()`); `reverse()` returns `ReverseResults`, which adds `areas()`. See
  [usage](docs/usage.md#collections).
- `account()->lazy()->requests()` returns every entry of the request log as a
  Laravel `LazyCollection` that requests the pages as it goes. See [usage](docs/usage.md#collections).
- Caching of answers in a Laravel cache store: `HOUSEPEDIA_CACHE`, `HOUSEPEDIA_CACHE_STORE`,
  `HOUSEPEDIA_CACHE_PREFIX` and `HOUSEPEDIA_CACHE_MAX_AGE`, and `withMaxCacheAge()` and
  `withoutCache()` on the facade, the client and every resource. See
  [configuration](docs/configuration.md#caching).
- Requests go through Laravel's HTTP client, so `Http::fake()`, `Http::assertSent()`, global
  `Http` middleware and the `Http` events (Telescope, Pulse) apply to them. See
  [testing](docs/testing.md#faking-http-responses).
- `Housepedia::fake()` answers every endpoint with an example response, with responses per
  `Endpoint` (merged arrays, `FakeResponse::json()`, `FakeResponse::error()`, closures and
  sequences) and `assertSent()`, `assertNotSent()`, `assertSentCount()` and
  `assertNothingSent()`. See [testing](docs/testing.md#faking-housepedia).

[1.0.0]: https://github.com/henzeb/housepedia-data-intelligence-laravel/releases/tag/v1.0.0
