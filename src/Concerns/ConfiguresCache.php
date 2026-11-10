<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns;

trait ConfiguresCache
{
    public function withMaxCacheAge(int $seconds): static
    {
        return new static($this->resource->withMaxCacheAge($seconds));
    }

    public function withoutCache(): static
    {
        return new static($this->resource->withoutCache());
    }
}
