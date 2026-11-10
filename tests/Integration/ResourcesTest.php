<?php

use GuzzleHttp\Psr7\Response as PsrResponse;
use Henzeb\Housepedia\Data\Intelligence\V5\DTO;
use Henzeb\Housepedia\Data\Intelligence\V5\Enums\PropertyFieldGroup;
use Henzeb\Housepedia\Data\Intelligence\V5\Enums\ReferenceSort;
use Henzeb\Housepedia\Data\Intelligence\V5\Enums\SuggestionType;
use Henzeb\Housepedia\Data\Intelligence\V5\Enums\TrendMetric;
use Henzeb\Housepedia\Data\Intelligence\V5\Enums\UsageGrouping;
use Henzeb\Housepedia\Data\Intelligence\V5\Input\ScenarioRow;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;
use Henzeb\Housepedia\Data\Intelligence\Laravel\ReverseResults;
use Henzeb\Housepedia\Data\Intelligence\Locators\AreaLocator;
use Henzeb\Housepedia\Data\Intelligence\Locators\Locator;
use Illuminate\Support\Collection;

it('forwards every endpoint to the core sdk', function (
    string $fixture,
    Closure $call,
    string $method,
    string $path,
    array $query,
    string $expected,
    bool $collection,
) {
    respondWith($fixture);

    $result = $call();
    $request = lastRequest();

    expect($request->getMethod())->toBe($method)
        ->and($request->getUri()->getPath())->toBe($path)
        ->and(queryOf($request))->toBe($query)
        ->and($this->http->getRequests())->toHaveCount(1);

    if ($collection) {
        expect($result)->toBeInstanceOf(Results::class)
            ->and($result->keys()->all())->toBe(range(0, $result->count() - 1))
            ->and($result->meta())->toBeInstanceOf(DTO\Meta::class)
            ->and($result)->not->toBeEmpty()
            ->each->toBeInstanceOf($expected);

        return;
    }

    expect($result)->toBeInstanceOf($expected);
})->with([
    'address suggest' => ['suggestAddresses', fn () => Housepedia::addresses()->suggest('1076LJ', 5, [SuggestionType::Address]), 'GET', '/v5/addresses/suggest', ['q' => '1076LJ', 'limit' => '5', 'types' => 'address'], DTO\AddressSuggestion::class, true],
    'address reverse strings' => ['reverseGeocode', fn () => Housepedia::addresses()->reverse('52.345360320000001', '-4.80'), 'GET', '/v5/addresses/reverse', ['lat' => '52.345360320000001', 'lon' => '-4.80'], DTO\ReverseAddress::class, true],
    'area lookup coordinate strings' => ['lookupArea', fn () => Housepedia::areas()->lookupCoordinates('52.35', '4.87'), 'GET', '/v5/areas/lookup', ['lat' => '52.35', 'lon' => '4.87'], DTO\AreaHierarchy::class, false],
    'address reverse' => ['reverseGeocode', fn () => Housepedia::addresses()->reverse(52.35, 4.87, 100, 3), 'GET', '/v5/addresses/reverse', ['lat' => '52.35', 'lon' => '4.87', 'radius_m' => '100', 'limit' => '3'], DTO\ReverseAddress::class, true],
    'address find' => ['getAddress', fn () => Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find(true), 'GET', '/v5/addresses/1076LJ-12-2', ['include' => 'rd'], DTO\Address::class, false],
    'address bag id' => ['getAddress', fn () => Housepedia::addresses()->withBagId('0363200000028422')->find(), 'GET', '/v5/addresses/bag:0363200000028422', [], DTO\Address::class, false],
    'address locator' => ['getAddress', fn () => Housepedia::addresses()->withLocator(Locator::bagId('0363200000028422'))->find(), 'GET', '/v5/addresses/bag:0363200000028422', [], DTO\Address::class, false],
    'address find all' => ['getAddressAll', fn () => Housepedia::addresses()->withPostcode('1076LJ', 12)->findAll(true), 'GET', '/v5/addresses/1076LJ-12', ['all' => 'true', 'include' => 'rd'], DTO\Address::class, true],
    'property' => ['getProperty', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->find([PropertyFieldGroup::Energy], true), 'GET', '/v5/properties/1076LJ-12-2', ['fields' => 'energy', 'include' => 'rd'], DTO\Property::class, false],
    'property locator' => ['getProperty', fn () => Housepedia::properties()->withLocator(Locator::postcode('1076LJ', 12, '2'))->find(), 'GET', '/v5/properties/1076LJ-12-2', [], DTO\Property::class, false],
    'energy label' => ['getEnergyLabel', fn () => Housepedia::properties()->withBagId('0363200000028422')->energyLabel(), 'GET', '/v5/properties/bag:0363200000028422/energy-label', [], DTO\EnergyLabel::class, false],
    'woz' => ['getWoz', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->woz(true), 'GET', '/v5/properties/1076LJ-12-2/woz', ['index' => 'true'], DTO\Woz::class, false],
    'condition' => ['getCondition', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->condition(), 'GET', '/v5/properties/1076LJ-12-2/condition', [], DTO\Condition::class, false],
    'transactions' => ['getTransactions', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->transactions(), 'GET', '/v5/properties/1076LJ-12-2/transactions', [], DTO\Transactions::class, false],
    'valuation' => ['getValuation', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation(new DateTimeImmutable('2026-08-01')), 'GET', '/v5/properties/1076LJ-12-2/valuation', ['as_of_date' => '2026-08-01'], DTO\Valuation::class, false],
    'valuation plus' => ['getValuationPlus', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuationPlus(new DateTimeImmutable('2026-08-01')), 'GET', '/v5/properties/1076LJ-12-2/valuation/plus', ['as_of_date' => '2026-08-01'], DTO\ValuationPlus::class, false],
    'valuation history' => ['getValuationHistory', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuationHistory(new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2026-01-01'), true), 'GET', '/v5/properties/1076LJ-12-2/valuation/history', ['from' => '2024-01-01', 'to' => '2026-01-01', 'include' => 'rd'], DTO\ValuationHistory::class, false],
    'references' => ['getReferences', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->references(5, 1000, 12, false, 60, 120, 1950, 2000, new DateTimeImmutable('2026-10-01'), ReferenceSort::Date), 'GET', '/v5/properties/1076LJ-12-2/references', ['limit' => '5', 'max_distance_m' => '1000', 'max_age_months' => '12', 'same_type' => 'false', 'min_living_area_m2' => '60', 'max_living_area_m2' => '120', 'min_construction_year' => '1950', 'max_construction_year' => '2000', 'as_of_date' => '2026-10-01', 'sort' => 'date'], DTO\References::class, false],
    'surroundings' => ['getSurroundings', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->surroundings(), 'GET', '/v5/properties/1076LJ-12-2/surroundings', [], DTO\Surroundings::class, false],
    'environment' => ['getEnvironment', fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->environment(), 'GET', '/v5/properties/1076LJ-12-2/environment', [], DTO\Environment::class, false],
    'building' => ['getBuilding', fn () => Housepedia::buildings()->withBagPandId('0363100012345678')->find(), 'GET', '/v5/buildings/0363100012345678', [], DTO\Building::class, false],
    'area lookup postcode' => ['lookupArea', fn () => Housepedia::areas()->lookupPostcode('1076LJ'), 'GET', '/v5/areas/lookup', ['postcode' => '1076LJ'], DTO\AreaHierarchy::class, false],
    'area lookup coordinates' => ['lookupArea', fn () => Housepedia::areas()->lookupCoordinates(52.35, 4.87), 'GET', '/v5/areas/lookup', ['lat' => '52.35', 'lon' => '4.87'], DTO\AreaHierarchy::class, false],
    'area' => ['getArea', fn () => Housepedia::areas()->withMunicipality('GM0363')->find(true), 'GET', '/v5/areas/municipality:GM0363', ['include' => 'rd'], DTO\Area::class, false],
    'area locator' => ['getArea', fn () => Housepedia::areas()->withArea(AreaLocator::municipality('GM0363'))->find(), 'GET', '/v5/areas/municipality:GM0363', [], DTO\Area::class, false],
    'area neighbourhood' => ['getAreaStats', fn () => Housepedia::areas()->withNeighbourhood('BU0363KH05')->stats(), 'GET', '/v5/areas/neighbourhood:BU0363KH05/stats', [], DTO\AreaStatistics::class, false],
    'area district' => ['getAreaDemographics', fn () => Housepedia::areas()->withDistrict('WK0363KH')->demographics(), 'GET', '/v5/areas/district:WK0363KH/demographics', [], DTO\Demographics::class, false],
    'area province' => ['getArea', fn () => Housepedia::areas()->withProvince('PV27')->find(), 'GET', '/v5/areas/province:PV27', [], DTO\Area::class, false],
    'area postcode4' => ['getArea', fn () => Housepedia::areas()->withPostcode4('1076')->find(), 'GET', '/v5/areas/postcode4:1076', [], DTO\Area::class, false],
    'area postcode6' => ['getArea', fn () => Housepedia::areas()->withPostcode6('1076LJ')->find(), 'GET', '/v5/areas/postcode6:1076LJ', [], DTO\Area::class, false],
    'area trends' => ['getAreaTrends', fn () => Housepedia::areas()->national()->trends(TrendMetric::TransactionPricePerSquareMeter, new DateTimeImmutable('2022-01-01'), new DateTimeImmutable('2026-01-01'), true), 'GET', '/v5/areas/national/trends', ['metric' => 'transaction_price_per_m2', 'from' => '2022-01-01', 'to' => '2026-01-01', 'include' => 'rd'], DTO\AreaTrends::class, false],
    'scenarios' => ['createScenarioValuations', fn () => Housepedia::valuations()->scenarios(new Collection([new ScenarioRow(Locator::postcode('1076LJ', 12, '2'))])), 'POST', '/v5/valuations/scenarios', [], DTO\ScenarioValuation::class, true],
    'account' => ['getAccount', fn () => Housepedia::account()->details(), 'GET', '/v5/account', [], DTO\Account::class, false],
    'account usage' => ['getAccountUsage', fn () => Housepedia::account()->usage(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31'), [UsageGrouping::Key]), 'GET', '/v5/account/usage', ['from' => '2026-10-01', 'to' => '2026-10-31', 'group_by' => 'key'], DTO\Usage::class, true],
    'account requests' => ['getAccountRequests', fn () => Housepedia::account()->requests(new DateTimeImmutable('2026-10-01T00:00:00+00:00'), new DateTimeImmutable('2026-10-02T00:00:00+00:00'), 'key_1', 200, 10, 'next'), 'GET', '/v5/account/requests', ['from' => '2026-10-01T00:00:00+00:00', 'to' => '2026-10-02T00:00:00+00:00', 'key_id' => 'key_1', 'status' => '200', 'limit' => '10', 'cursor' => 'next'], DTO\RequestLogEntry::class, true],
    'catalog' => ['getCatalog', fn () => Housepedia::catalog(), 'GET', '/v5/catalog', [], DTO\Catalog\Catalog::class, false],
]);

it('checks whether a home has transactions', function () {
    $this->http->addResponse(new PsrResponse(200));
    $this->http->addResponse(new PsrResponse(404));

    $home = Housepedia::properties()->withBagId('0363200000028422');

    expect($home->hasTransactions())->toBeTrue()
        ->and(lastRequest()->getMethod())->toBe('HEAD')
        ->and(lastRequest()->getUri()->getPath())->toBe('/v5/properties/bag:0363200000028422/transactions')
        ->and($home->hasTransactions())->toBeFalse()
        ->and($this->http->getRequests())->toHaveCount(2);
});

it('sends scenario rows from a collection', function () {
    respondWith('createScenarioValuations');

    Housepedia::valuations()->scenarios(collect([new ScenarioRow(Locator::bagId('0363200000028422'))]));

    expect(json_decode((string)lastRequest()->getBody(), true))
        ->toBe(['data' => [['locator' => 'bag:0363200000028422', 'scenarios' => []]]]);
});

it('sends scenario rows from a keyed collection as a list', function () {
    respondWith('createScenarioValuations');

    Housepedia::valuations()->scenarios(collect(['home' => new ScenarioRow(Locator::bagId('0363200000028422'))]));

    expect((string)lastRequest()->getBody())->toBe('{"data":[{"locator":"bag:0363200000028422","scenarios":[]}]}');
});

it('returns the areas and the meta of the reverse answer', function () {
    respondWith('reverseGeocode');

    $nearby = Housepedia::addresses()->reverse(52.35, 4.87);

    expect($nearby)->toBeInstanceOf(ReverseResults::class)
        ->and($nearby->areas())->toBeInstanceOf(DTO\AreaHierarchy::class)
        ->and($nearby->meta()?->requestId())->toBe(jsonFixture('reverseGeocode')['meta']['request_id'])
        ->and($nearby->data())->each->toBeInstanceOf(DTO\ReverseAddress::class);
});

it('keeps the rate limit of the answer in the meta of the results', function () {
    $this->http->addResponse(jsonResponse(jsonFixture('suggestAddresses'), 200, [
        'X-RateLimit-Limit' => '20',
        'X-RateLimit-Remaining' => '17',
        'X-RateLimit-Reset' => '1',
    ]));

    expect(Housepedia::addresses()->suggest('1076LJ')->meta()?->rateLimit()?->remaining())->toBe(17);
});

it('creates collections without meta from collection methods', function () {
    respondWith('suggestAddresses');

    $suggestions = Housepedia::addresses()->suggest('1076LJ');
    $filtered = $suggestions->filter(fn () => true);

    expect($suggestions->meta())->not->toBeNull()
        ->and($filtered)->toBeInstanceOf(Results::class)
        ->and($filtered->meta())->toBeNull()
        ->and($filtered->all())->toBe($suggestions->all());
});
