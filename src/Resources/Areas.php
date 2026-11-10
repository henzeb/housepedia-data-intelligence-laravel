<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use DateTimeInterface;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Areas as CoreAreas;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Area;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\AreaHierarchy;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\AreaStatistics;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\AreaTrends;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Demographics;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Enums\TrendMetric;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;
use Henzeb\Housepedia\Data\Intelligence\Locators\AreaLocator;

/**
 * @phpstan-consistent-constructor
 */
class Areas implements CoreAreas
{
    use ConfiguresCache;

    public function __construct(
        private CoreAreas $resource,
    ) {
    }

    public function withArea(AreaLocator $area): static
    {
        return new static($this->resource->withArea($area));
    }

    public function withNeighbourhood(string $code): static
    {
        return new static($this->resource->withNeighbourhood($code));
    }

    public function withDistrict(string $code): static
    {
        return new static($this->resource->withDistrict($code));
    }

    public function withMunicipality(string $code): static
    {
        return new static($this->resource->withMunicipality($code));
    }

    public function withProvince(string $code): static
    {
        return new static($this->resource->withProvince($code));
    }

    public function withPostcode4(string $postcode): PostcodeArea
    {
        return new PostcodeArea($this->resource->withPostcode4($postcode));
    }

    public function withPostcode6(string $postcode): PostcodeArea
    {
        return new PostcodeArea($this->resource->withPostcode6($postcode));
    }

    public function national(): static
    {
        return new static($this->resource->national());
    }

    public function lookupPostcode(string $postcode): AreaHierarchy
    {
        return $this->resource->lookupPostcode($postcode);
    }

    public function lookupCoordinates(float|string $latitude, float|string $longitude): AreaHierarchy
    {
        return $this->resource->lookupCoordinates($latitude, $longitude);
    }

    public function find(bool $includeRd = false): Area
    {
        return $this->resource->find($includeRd);
    }

    public function stats(): AreaStatistics
    {
        return $this->resource->stats();
    }

    public function trends(
        TrendMetric|string|null $metric = null,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        bool $includeRd = false,
    ): AreaTrends {
        return $this->resource->trends($metric, $from, $to, $includeRd);
    }

    public function demographics(): Demographics
    {
        return $this->resource->demographics();
    }
}
