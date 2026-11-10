# Configuration

The package reads its settings from `config/housepedia.php`. Publish the file to change it:

```bash
php artisan vendor:publish --tag=housepedia-config
```

| Key                         | Environment variable                   | Default |
|-----------------------------|----------------------------------------|---------|
| `api_key`                   | `HOUSEPEDIA_API_KEY`                   |         |
| `base_url`                  | `HOUSEPEDIA_BASE_URL`                  |         |
| `version`                   | `HOUSEPEDIA_VERSION`                   | `v5`    |
| `language`                  | `HOUSEPEDIA_LANGUAGE`                  |         |
| `timeout`                   | `HOUSEPEDIA_TIMEOUT`                   | `30`    |
| `connect_timeout`           | `HOUSEPEDIA_CONNECT_TIMEOUT`           | `10`    |
| `retry.retries`             | `HOUSEPEDIA_RETRIES`                   | `2`     |
| `retry.service_unavailable` | `HOUSEPEDIA_RETRY_SERVICE_UNAVAILABLE` | `false` |
| `cache.enabled`             | `HOUSEPEDIA_CACHE`                     | `false` |
| `cache.store`               | `HOUSEPEDIA_CACHE_STORE`               |         |
| `cache.prefix`              | `HOUSEPEDIA_CACHE_PREFIX`              | `housepedia-data-intelligence` |
| `cache.max_age_in_seconds`  | `HOUSEPEDIA_CACHE_MAX_AGE`             |         |

Without `api_key`, only `catalog()` works; other calls throw a `MissingApiKeyException`.
Without `base_url`, requests go to `https://huispedia.dev`. `language` is sent as
`Accept-Language`; the API writes the `detail` of errors in this language (`en` or `nl`).
`version` pins the API version (`5`, `v5`), `v5` by default.
`Housepedia::withVersion('v5')` sets it for one call chain. See
[API versions](https://github.com/henzeb/housepedia-data-intelligence/blob/main/docs/getting-started.md#api-versions).
The timeouts are in seconds.

## Retrying

A rate-limited request (429) is retried `retry.retries` times; `0` never retries. Between
attempts the client waits the `Retry-After` seconds of the API, or one second when it sends
none. Set `retry.service_unavailable` to `true` to also retry a 503. When the last attempt
fails, the exception is thrown.

The waiting goes through Laravel's `Sleep`, so `Sleep::fake()` skips it in tests:

```php
use Illuminate\Support\Sleep;

Sleep::fake();

// code under test

Sleep::assertSleptTimes(1);
```

## Caching

The API says per answer how long it may be reused: valuations not at all, characteristics an
hour, area statistics a day (see [rate limits](https://huispedia.dev/en/docs/rate-limits/)). With `HOUSEPEDIA_CACHE=true`, answers are kept in a Laravel cache
store for that time. A cached answer is read without a request and does not count toward the rate
limit. `HOUSEPEDIA_CACHE_MAX_AGE` shortens the time; an answer is never kept longer than the
API allows. Without `HOUSEPEDIA_CACHE_STORE` the default cache store is used.

```dotenv
HOUSEPEDIA_CACHE=true
HOUSEPEDIA_CACHE_STORE=redis
HOUSEPEDIA_CACHE_MAX_AGE=600
```

A store picks its own connection. To keep the answers on a separate Redis connection, add a
connection and a store for it:

```php
// config/database.php
'redis' => [
    // ...
    'housepedia' => [
        'host' => env('HOUSEPEDIA_REDIS_HOST', '127.0.0.1'),
        'port' => env('HOUSEPEDIA_REDIS_PORT', 6379),
        'database' => env('HOUSEPEDIA_REDIS_DB', 3),
    ],
],

// config/cache.php
'stores' => [
    // ...
    'housepedia' => [
        'driver' => 'redis',
        'connection' => 'housepedia',
    ],
],
```

```dotenv
HOUSEPEDIA_CACHE_STORE=housepedia
```

Per call, `withMaxCacheAge()` and `withoutCache()` change it on a resource or the facade:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;

$home = Housepedia::properties()->withPostcode('1076LJ', 12, '2');

$home->withMaxCacheAge(60)->find();     // kept for at most 60 seconds
$home->withoutCache()->find();          // always sent to the API

Housepedia::withoutCache()->get('/v5/account');
```

The keys start with `HOUSEPEDIA_CACHE_PREFIX` and a dot. Keep the characters `{}()/\@:` out
of the prefix. The details are in the
[core documentation](https://github.com/henzeb/housepedia-data-intelligence/blob/main/docs/caching.md).

## HTTP client

Requests are sent with Laravel's HTTP client using the configured timeouts, so
`Http::fake()`, `Http::preventStrayRequests()`, global `Http` middleware and the `Http`
events, which Telescope and Pulse record, apply to them. Answers from the cache send no
request.

The client is bound in the container as `HousepediaServiceProvider::HTTP_CLIENT`. Bind any
PSR-18 client to that key to replace it:

```php
use Henzeb\Housepedia\Data\Intelligence\Laravel\HousepediaServiceProvider;

$this->app->singleton(HousepediaServiceProvider::HTTP_CLIENT, fn () => new MyPsr18Client());
```
