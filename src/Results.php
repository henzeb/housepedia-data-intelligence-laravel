<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel;

use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Meta;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Results as ResultsContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * @template-covariant TValue
 * @extends Collection<int, TValue>
 * @implements ResultsContract<TValue>
 */
class Results extends Collection implements ResultsContract
{
    /**
     * @param Arrayable<int, TValue>|iterable<int, TValue>|null $items
     */
    public function __construct(
        $items = [],
        private ?Meta $meta = null,
    ) {
        parent::__construct($items);
    }

    public function meta(): ?Meta
    {
        return $this->meta;
    }

    /**
     * @return list<TValue>
     */
    public function data(): array
    {
        return array_values($this->items);
    }
}
