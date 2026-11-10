<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use Henzeb\Housepedia\Data\Intelligence\Contracts\Buildings as CoreBuildings;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Building;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;

/**
 * @phpstan-consistent-constructor
 */
class Buildings implements CoreBuildings
{
    use ConfiguresCache;

    public function __construct(
        private CoreBuildings $resource,
    ) {
    }

    public function withBagPandId(string $bagPandId): static
    {
        return new static($this->resource->withBagPandId($bagPandId));
    }

    public function find(): Building
    {
        return $this->resource->find();
    }
}
