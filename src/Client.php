<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel;

use Henzeb\Housepedia\Data\Intelligence\Client as CoreClient;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Catalog\Catalog;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Account;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Addresses;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Areas;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Buildings;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Properties;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources\Valuations;
use Henzeb\Housepedia\Data\Intelligence\Response as CoreResponse;

/**
 * @phpstan-consistent-constructor
 */
class Client
{
    public function __construct(
        private CoreClient $client,
    ) {
    }

    public function addresses(): Addresses
    {
        return new Addresses($this->client->addresses());
    }

    public function properties(): Properties
    {
        return new Properties($this->client->properties());
    }

    public function buildings(): Buildings
    {
        return new Buildings($this->client->buildings());
    }

    public function areas(): Areas
    {
        return new Areas($this->client->areas());
    }

    public function valuations(): Valuations
    {
        return new Valuations($this->client->valuations());
    }

    public function account(): Account
    {
        return new Account($this->client->account());
    }

    public function version(): string
    {
        return $this->client->version();
    }

    public function withVersion(int|string $version): static
    {
        return new static($this->client->withVersion($version));
    }

    public function withMaxCacheAge(int $seconds): static
    {
        return new static($this->client->withMaxCacheAge($seconds));
    }

    public function withoutCache(): static
    {
        return new static($this->client->withoutCache());
    }

    public function catalog(): Catalog
    {
        return $this->client->catalog();
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->response($this->client->get($path, $query));
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string|int|float|bool|null> $query
     */
    public function post(string $path, array $body, array $query = []): Response
    {
        return $this->response($this->client->post($path, $body, $query));
    }

    public function core(): CoreClient
    {
        return $this->client;
    }

    private function response(CoreResponse $response): Response
    {
        return new Response($response->status(), $response->headers(), $response->raw());
    }
}
