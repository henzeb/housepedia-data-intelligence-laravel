<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class HousepediaFake implements ClientInterface
{
    /**
     * @var array<string, list<array<array-key, mixed>|FakeResponse|Closure>>
     */
    private array $responses = [];

    /**
     * @var list<RecordedRequest>
     */
    private array $recorded = [];

    /**
     * @param array<string, array<array-key, mixed>|FakeResponse|Closure> $responses
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $endpoint => $response) {
            $this->respond(Endpoint::from($endpoint), $response);
        }
    }

    /**
     * @param array<array-key, mixed>|FakeResponse|Closure ...$responses
     */
    public function respond(Endpoint $endpoint, array|FakeResponse|Closure ...$responses): static
    {
        $this->responses[$endpoint->value] = array_values($responses);

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $endpoint = Endpoint::match($request->getMethod(), rawurldecode($request->getUri()->getPath()))
            ?? throw new StrayRequestException($request);

        $recorded = new RecordedRequest($endpoint, $request);
        $this->recorded[] = $recorded;

        return $this->resolve($recorded, $this->next($endpoint))
            ->toPsrResponse($request->getMethod() !== 'HEAD');
    }

    /**
     * @return Collection<int, RecordedRequest>
     */
    public function recorded(?Endpoint $endpoint = null, ?Closure $callback = null): Collection
    {
        return (new Collection($this->recorded))
            ->filter(fn (RecordedRequest $request): bool => $endpoint === null || $request->endpoint() === $endpoint)
            ->filter(fn (RecordedRequest $request): bool => $callback === null || $callback($request) === true)
            ->values();
    }

    public function assertSent(Endpoint $endpoint, ?Closure $callback = null): void
    {
        Assert::assertTrue(
            $this->recorded($endpoint, $callback)->isNotEmpty(),
            sprintf('An expected request to the Housepedia endpoint [%s] was not sent.', $endpoint->value),
        );
    }

    public function assertNotSent(Endpoint $endpoint, ?Closure $callback = null): void
    {
        Assert::assertTrue(
            $this->recorded($endpoint, $callback)->isEmpty(),
            sprintf('An unexpected request to the Housepedia endpoint [%s] was sent.', $endpoint->value),
        );
    }

    public function assertSentCount(int $count, ?Endpoint $endpoint = null): void
    {
        Assert::assertCount(
            $count,
            $this->recorded($endpoint),
            $endpoint === null
                ? 'The number of requests sent to Housepedia does not match.'
                : sprintf('The number of requests sent to the Housepedia endpoint [%s] does not match.', $endpoint->value),
        );
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->recorded, 'Requests were sent to Housepedia.');
    }

    /**
     * @return array<array-key, mixed>|FakeResponse|Closure|null
     */
    private function next(Endpoint $endpoint): array|FakeResponse|Closure|null
    {
        $responses = $this->responses[$endpoint->value] ?? [];

        if (count($responses) > 1) {
            return array_shift($this->responses[$endpoint->value]);
        }

        return $responses[0] ?? null;
    }

    /**
     * @param array<array-key, mixed>|FakeResponse|Closure|null $response
     */
    private function resolve(RecordedRequest $request, array|FakeResponse|Closure|null $response): FakeResponse
    {
        if ($response instanceof Closure) {
            return $this->resolve($request, $this->closureResponse($response($request)));
        }

        if ($response instanceof FakeResponse) {
            return $response;
        }

        return new FakeResponse($this->merge($this->example($request), $response ?? []));
    }

    /**
     * @return array<array-key, mixed>|FakeResponse
     */
    private function closureResponse(mixed $response): array|FakeResponse
    {
        if (is_array($response) || $response instanceof FakeResponse) {
            return $response;
        }

        throw new UnexpectedValueException('A Housepedia fake response closure must return an array or a FakeResponse.');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function example(RecordedRequest $request): array
    {
        $fixture = $request->endpoint() === Endpoint::Address && $request->query('all') !== null
            ? 'getAddressAll'
            : $request->endpoint()->fixture();

        $example = json_decode(
            (string)file_get_contents(dirname(__DIR__, 2) . '/resources/fixtures/v5/' . $fixture . '.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return is_array($example) ? $example : [];
    }

    /**
     * @param array<array-key, mixed> $example
     * @param array<array-key, mixed> $overrides
     * @return array<array-key, mixed>
     */
    private function merge(array $example, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $example[$key] = is_array($value) && ! array_is_list($value) && isset($example[$key]) && is_array($example[$key]) && ! array_is_list($example[$key])
                ? $this->merge($example[$key], $value)
                : $value;
        }

        return $example;
    }
}
