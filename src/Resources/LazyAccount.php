<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use DateTimeInterface;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\RequestLogEntry;
use Henzeb\Housepedia\Data\Intelligence\Contracts\LazyAccount as CoreLazyAccount;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;
use Illuminate\Support\LazyCollection;

/**
 * @phpstan-consistent-constructor
 */
class LazyAccount implements CoreLazyAccount
{
    use ConfiguresCache;

    public function __construct(
        private CoreLazyAccount $resource,
    ) {
    }

    /**
     * @return LazyCollection<int, RequestLogEntry>
     */
    public function requests(
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        ?string $keyId = null,
        ?int $status = null,
        ?int $limit = null,
    ): LazyCollection {
        return new LazyCollection(fn () => yield from $this->resource->requests($from, $to, $keyId, $status, $limit));
    }
}
