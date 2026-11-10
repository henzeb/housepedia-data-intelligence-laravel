<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use DateTimeInterface;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Account as CoreAccount;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Account as AccountDetails;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\RequestLogEntry;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Usage;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Enums\UsageGrouping;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;

/**
 * @phpstan-consistent-constructor
 */
class Account implements CoreAccount
{
    use ConfiguresCache;

    public function __construct(
        private CoreAccount $resource,
    ) {
    }

    public function lazy(): LazyAccount
    {
        return new LazyAccount($this->resource->lazy());
    }

    public function details(): AccountDetails
    {
        return $this->resource->details();
    }

    /**
     * @param list<UsageGrouping|string> $groupBy
     * @return Results<Usage>
     */
    public function usage(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, array $groupBy = []): Results
    {
        $results = $this->resource->usage($from, $to, $groupBy);

        return new Results($results->data(), $results->meta());
    }

    /**
     * @return Results<RequestLogEntry>
     */
    public function requests(
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        ?string $keyId = null,
        ?int $status = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Results {
        $results = $this->resource->requests($from, $to, $keyId, $status, $limit, $cursor);

        return new Results($results->data(), $results->meta());
    }
}
