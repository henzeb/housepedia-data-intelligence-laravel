# Differences from the core SDK

| Core SDK                                   | Laravel package                                     |
|--------------------------------------------|-----------------------------------------------------|
| `Client::create($apiKey)`                  | Configured from `config/housepedia.php`             |
| `Henzeb\Housepedia\Data\Intelligence\Client`    | `Henzeb\Housepedia\Data\Intelligence\Laravel\Client` and the `Housepedia` facade |
| List endpoints return `Results<T>`         | List endpoints return `Results<T>`, a Laravel `Collection<int, T>` with `meta()` |
| `account()->lazy()->requests()` returns a `Generator` | `account()->lazy()->requests()` returns a Laravel `LazyCollection` |
| `valuations()->scenarios(array $rows)`     | `valuations()->scenarios(iterable $rows)`           |
| `get()`/`post()` return `Response`         | `get()`/`post()` return a `Response` with `collect()` |
| Retries wait with `sleep()`                | Retries use `retry()`, so `Sleep::fake()` skips the wait |
| `Config(cache: $psr16Cache)`               | `HOUSEPEDIA_CACHE=true` with a Laravel cache store  |
| A discovered PSR-18 client                 | Laravel's HTTP client, so `Http::fake()` and the `Http` events apply |
|                                            | `core()` returns the core client                    |

The method names, arguments, locators, response objects and exceptions are the same.
