<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;

use DateTimeInterface;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Condition;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\EnergyLabel;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Environment;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Property;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\References;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Surroundings;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Transactions;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Valuation;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\ValuationHistory;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\ValuationPlus;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Woz;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Enums\PropertyFieldGroup;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Enums\ReferenceSort;
use Henzeb\Housepedia\Data\Intelligence\Contracts\Properties as CoreProperties;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Concerns\ConfiguresCache;
use Henzeb\Housepedia\Data\Intelligence\Locators\Locator;

/**
 * @phpstan-consistent-constructor
 */
class Properties implements CoreProperties
{
    use ConfiguresCache;

    public function __construct(
        private CoreProperties $resource,
    ) {
    }

    public function withLocator(Locator $locator): static
    {
        return new static($this->resource->withLocator($locator));
    }

    public function withPostcode(string $postcode, int $houseNumber, ?string $extension = null): static
    {
        return new static($this->resource->withPostcode($postcode, $houseNumber, $extension));
    }

    public function withBagId(string $bagId): static
    {
        return new static($this->resource->withBagId($bagId));
    }

    /**
     * @param list<PropertyFieldGroup|string> $fields
     */
    public function find(array $fields = [], bool $includeRd = false): Property
    {
        return $this->resource->find($fields, $includeRd);
    }

    public function energyLabel(): EnergyLabel
    {
        return $this->resource->energyLabel();
    }

    public function woz(bool $indexed = false): Woz
    {
        return $this->resource->woz($indexed);
    }

    public function condition(): Condition
    {
        return $this->resource->condition();
    }

    public function transactions(): Transactions
    {
        return $this->resource->transactions();
    }

    public function hasTransactions(): bool
    {
        return $this->resource->hasTransactions();
    }

    public function valuation(?DateTimeInterface $asOfDate = null): Valuation
    {
        return $this->resource->valuation($asOfDate);
    }

    public function valuationPlus(?DateTimeInterface $asOfDate = null): ValuationPlus
    {
        return $this->resource->valuationPlus($asOfDate);
    }

    public function valuationHistory(
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        bool $includeRd = false,
    ): ValuationHistory {
        return $this->resource->valuationHistory($from, $to, $includeRd);
    }

    public function references(
        ?int $limit = null,
        ?int $maxDistanceInMeters = null,
        ?int $maxAgeMonths = null,
        ?bool $sameType = null,
        ?int $minLivingAreaInSquareMeters = null,
        ?int $maxLivingAreaInSquareMeters = null,
        ?int $minConstructionYear = null,
        ?int $maxConstructionYear = null,
        ?DateTimeInterface $asOfDate = null,
        ReferenceSort|string|null $sort = null,
    ): References {
        return $this->resource->references(
            $limit,
            $maxDistanceInMeters,
            $maxAgeMonths,
            $sameType,
            $minLivingAreaInSquareMeters,
            $maxLivingAreaInSquareMeters,
            $minConstructionYear,
            $maxConstructionYear,
            $asOfDate,
            $sort,
        );
    }

    public function surroundings(): Surroundings
    {
        return $this->resource->surroundings();
    }

    public function environment(): Environment
    {
        return $this->resource->environment();
    }
}
