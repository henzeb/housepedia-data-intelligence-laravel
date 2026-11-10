<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use Henzeb\Housepedia\Data\Intelligence\Contracts\Addresses as CoreAddresses;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Address;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\AddressSuggestion;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Enums\SuggestionType;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;
use Henzeb\Housepedia\Data\Intelligence\Laravel\ReverseResults;
use Henzeb\Housepedia\Data\Intelligence\Locators\Locator;

/**
 * @phpstan-consistent-constructor
 */
class Addresses implements CoreAddresses
{
    use ConfiguresCache;

    public function __construct(
        private CoreAddresses $resource,
    ) {
    }

    public function withLocator(Locator $locator): static
    {
        return new static($this->resource->withLocator($locator));
    }

    public function withPostcode(string $postcode, int $houseNumber, ?string $extension = null): static
    {
        return new static($this->resource->withPostcode($postcode, $houseNumber, $extension));
    }

    public function withBagId(string $bagId): static
    {
        return new static($this->resource->withBagId($bagId));
    }

    public function find(bool $includeRd = false): Address
    {
        return $this->resource->find($includeRd);
    }

    /**
     * @return Results<Address>
     */
    public function findAll(bool $includeRd = false): Results
    {
        $results = $this->resource->findAll($includeRd);

        return new Results($results->data(), $results->meta());
    }

    /**
     * @param list<SuggestionType|string> $types
     * @return Results<AddressSuggestion>
     */
    public function suggest(string $query, ?int $limit = null, array $types = []): Results
    {
        $results = $this->resource->suggest($query, $limit, $types);

        return new Results($results->data(), $results->meta());
    }

    public function reverse(
        float|string $latitude,
        float|string $longitude,
        ?int $radiusInMeters = null,
        ?int $limit = null,
    ): ReverseResults {
        $results = $this->resource->reverse($latitude, $longitude, $radiusInMeters, $limit);

        return new ReverseResults($results->data(), $results->meta(), $results->areas());
    }
}
