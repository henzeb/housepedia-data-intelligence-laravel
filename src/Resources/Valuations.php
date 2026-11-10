<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\ScenarioValuation;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Input\ScenarioRow;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Valuations as CoreValuations;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;

/**
 * @phpstan-consistent-constructor
 */
class Valuations implements CoreValuations
{
    use ConfiguresCache;

    public function __construct(
        private CoreValuations $resource,
    ) {
    }

    /**
     * @param iterable<ScenarioRow> $rows
     * @return Results<ScenarioValuation>
     */
    public function scenarios(iterable $rows): Results
    {
        $results = $this->resource->scenarios(iterator_to_array($rows, false));

        return new Results($results->data(), $results->meta());
    }
}
