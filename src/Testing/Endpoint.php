<?php

namespace Henzeb\Housepedia\Data\Intelligence\Laravel\Testing;

enum Endpoint: string
{
    case AddressSuggest = 'address_suggest';
    case Address = 'address';
    case AddressReverse = 'address_reverse';
    case Property = 'property';
    case PropertyEnergyLabel = 'property_energy_label';
    case PropertyWoz = 'property_woz';
    case PropertyCondition = 'property_condition';
    case Building = 'building';
    case Valuation = 'valuation';
    case ValuationPlus = 'valuation_plus';
    case ValuationHistory = 'valuation_history';
    case ValuationScenarios = 'valuation_scenarios';
    case References = 'references';
    case Transactions = 'transactions';
    case Area = 'area';
    case AreaLookup = 'area_lookup';
    case AreaStats = 'area_stats';
    case AreaTrends = 'area_trends';
    case AreaDemographics = 'area_demographics';
    case Surroundings = 'surroundings';
    case Environment = 'environment';
    case Account = 'account';
    case AccountUsage = 'account_usage';
    case AccountRequests = 'account_requests';
    case Catalog = 'catalog';

    public static function match(string $method, string $path): ?self
    {
        $method = strtoupper($method);
        $cases = self::cases();

        usort($cases, fn (self $a, self $b): int => (int)$a->hasLocator() <=> (int)$b->hasLocator());

        foreach ($cases as $case) {
            if (in_array($method, $case->methods(), true) && preg_match($case->pattern(), $path) === 1) {
                return $case;
            }
        }

        return null;
    }

    public function path(): string
    {
        return match ($this) {
            self::AddressSuggest => '/v5/addresses/suggest',
            self::Address => '/v5/addresses/{locator}',
            self::AddressReverse => '/v5/addresses/reverse',
            self::Property => '/v5/properties/{locator}',
            self::PropertyEnergyLabel => '/v5/properties/{locator}/energy-label',
            self::PropertyWoz => '/v5/properties/{locator}/woz',
            self::PropertyCondition => '/v5/properties/{locator}/condition',
            self::Building => '/v5/buildings/{bag_pand_id}',
            self::Valuation => '/v5/properties/{locator}/valuation',
            self::ValuationPlus => '/v5/properties/{locator}/valuation/plus',
            self::ValuationHistory => '/v5/properties/{locator}/valuation/history',
            self::ValuationScenarios => '/v5/valuations/scenarios',
            self::References => '/v5/properties/{locator}/references',
            self::Transactions => '/v5/properties/{locator}/transactions',
            self::Area => '/v5/areas/{area}',
            self::AreaLookup => '/v5/areas/lookup',
            self::AreaStats => '/v5/areas/{area}/stats',
            self::AreaTrends => '/v5/areas/{area}/trends',
            self::AreaDemographics => '/v5/areas/{area}/demographics',
            self::Surroundings => '/v5/properties/{locator}/surroundings',
            self::Environment => '/v5/properties/{locator}/environment',
            self::Account => '/v5/account',
            self::AccountUsage => '/v5/account/usage',
            self::AccountRequests => '/v5/account/requests',
            self::Catalog => '/v5/catalog',
        };
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return match ($this) {
            self::ValuationScenarios => ['POST'],
            self::Transactions => ['GET', 'HEAD'],
            default => ['GET'],
        };
    }

    public function fixture(): string
    {
        return match ($this) {
            self::AddressSuggest => 'suggestAddresses',
            self::Address => 'getAddress',
            self::AddressReverse => 'reverseGeocode',
            self::Property => 'getProperty',
            self::PropertyEnergyLabel => 'getEnergyLabel',
            self::PropertyWoz => 'getWoz',
            self::PropertyCondition => 'getCondition',
            self::Building => 'getBuilding',
            self::Valuation => 'getValuation',
            self::ValuationPlus => 'getValuationPlus',
            self::ValuationHistory => 'getValuationHistory',
            self::ValuationScenarios => 'createScenarioValuations',
            self::References => 'getReferences',
            self::Transactions => 'getTransactions',
            self::Area => 'getArea',
            self::AreaLookup => 'lookupArea',
            self::AreaStats => 'getAreaStats',
            self::AreaTrends => 'getAreaTrends',
            self::AreaDemographics => 'getAreaDemographics',
            self::Surroundings => 'getSurroundings',
            self::Environment => 'getEnvironment',
            self::Account => 'getAccount',
            self::AccountUsage => 'getAccountUsage',
            self::AccountRequests => 'getAccountRequests',
            self::Catalog => 'getCatalog',
        };
    }

    private function hasLocator(): bool
    {
        return str_contains($this->path(), '{');
    }

    private function pattern(): string
    {
        return '#' . preg_replace('/\\\\\{[a-z_]+\\\\\}/', '[^/]+', preg_quote($this->path(), '#')) . '$#';
    }
}
