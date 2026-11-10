<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel;

use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\AreaHierarchy;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Meta;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\ReverseAddress;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\ReverseResults as ReverseResultsContract;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @extends Results<ReverseAddress>
 * @implements ReverseResultsContract<ReverseAddress>
 */
class ReverseResults extends Results implements ReverseResultsContract
{
    /**
     * @param Arrayable<int, ReverseAddress>|iterable<int, ReverseAddress>|null $items
     */
    public function __construct(
        $items = [],
        ?Meta $meta = null,
        private ?AreaHierarchy $areas = null,
    ) {
        parent::__construct($items, $meta);
    }

    public function areas(): ?AreaHierarchy
    {
        return $this->areas;
    }
}
