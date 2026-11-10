<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel;

use Henzeb\Housepedia\Data\Intelligence\Response as CoreResponse;
use Illuminate\Support\Collection;

class Response extends CoreResponse
{
    /**
     * @return Collection<array-key, mixed>
     */
    public function collect(?string $key = null): Collection
    {
        return new Collection($key === null ? $this->raw() : ($this->array($key) ?? []));
    }
}
