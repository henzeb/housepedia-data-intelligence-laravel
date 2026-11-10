<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Area;
use Henzeb\Housepedia\Data\Intelligence\Contracts\PostcodeArea as CorePostcodeArea;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;

/**
 * @phpstan-consistent-constructor
 */
class PostcodeArea implements CorePostcodeArea
{
    use ConfiguresCache;

    public function __construct(
        private CorePostcodeArea $resource,
    ) {
    }

    public function find(bool $includeRd = false): Area
    {
        return $this->resource->find($includeRd);
    }
}
