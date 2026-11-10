<?php

use GuzzleHttp\Psr7\Request as PsrRequest;
use Henzeb\Housepedia\Data\Intelligence\Client as CoreClient;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\AddressAmbiguousException;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\NotFoundException;
use Henzeb\Housepedia\Data\Intelligence\Exceptions\RateLimitException;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\HousepediaServiceProvider;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\Endpoint;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\FakeResponse;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\HousepediaFake;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\RecordedRequest;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Testing\StrayRequestException;
use Henzeb\Housepedia\Data\Intelligence\Locators\Locator;
use Henzeb\Housepedia\Data\Intelligence\V5\DTO;
use Henzeb\Housepedia\Data\Intelligence\V5\Enums\TrendMetric;
use Henzeb\Housepedia\Data\Intelligence\V5\Input\ScenarioRow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\AssertionFailedError;

it('answers every endpoint with its example response', function (Endpoint $endpoint, Closure $call, string $expected) {
    $fake = Housepedia::fake();

    $result = $call();

    expect($result instanceof Results ? $result->first() : $result)->toBeInstanceOf($expected)
        ->and($fake->recorded())->toHaveCount(1)
        ->and($fake->recorded()->first()?->endpoint())->toBe($endpoint);

    Housepedia::assertSent($endpoint);
})->with([
    'address suggest' => [Endpoint::AddressSuggest, fn () => Housepedia::addresses()->suggest('1076LJ'), DTO\AddressSuggestion::class],
    'address' => [Endpoint::Address, fn () => Housepedia::addresses()->withPostcode('1076LJ', 12, '2')->find(), DTO\Address::class],
    'address reverse' => [Endpoint::AddressReverse, fn () => Housepedia::addresses()->reverse(52.35, 4.87), DTO\ReverseAddress::class],
    'property' => [Endpoint::Property, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->find(), DTO\Property::class],
    'energy label' => [Endpoint::PropertyEnergyLabel, fn () => Housepedia::properties()->withBagId('0363200000028422')->energyLabel(), DTO\EnergyLabel::class],
    'woz' => [Endpoint::PropertyWoz, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->woz(), DTO\Woz::class],
    'condition' => [Endpoint::PropertyCondition, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->condition(), DTO\Condition::class],
    'building' => [Endpoint::Building, fn () => Housepedia::buildings()->withBagPandId('0363100012345678')->find(), DTO\Building::class],
    'valuation' => [Endpoint::Valuation, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation(), DTO\Valuation::class],
    'valuation plus' => [Endpoint::ValuationPlus, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuationPlus(), DTO\ValuationPlus::class],
    'valuation history' => [Endpoint::ValuationHistory, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuationHistory(), DTO\ValuationHistory::class],
    'scenarios' => [Endpoint::ValuationScenarios, fn () => Housepedia::valuations()->scenarios([new ScenarioRow(Locator::postcode('1076LJ', 12, '2'))]), DTO\ScenarioValuation::class],
    'references' => [Endpoint::References, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->references(), DTO\References::class],
    'transactions' => [Endpoint::Transactions, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->transactions(), DTO\Transactions::class],
    'area' => [Endpoint::Area, fn () => Housepedia::areas()->withMunicipality('GM0363')->find(), DTO\Area::class],
    'postcode area' => [Endpoint::Area, fn () => Housepedia::areas()->withPostcode6('1076LJ')->find(), DTO\Area::class],
    'area lookup' => [Endpoint::AreaLookup, fn () => Housepedia::areas()->lookupPostcode('1076LJ'), DTO\AreaHierarchy::class],
    'area stats' => [Endpoint::AreaStats, fn () => Housepedia::areas()->withNeighbourhood('BU0363KH05')->stats(), DTO\AreaStatistics::class],
    'area trends' => [Endpoint::AreaTrends, fn () => Housepedia::areas()->national()->trends(TrendMetric::TransactionPricePerSquareMeter), DTO\AreaTrends::class],
    'area demographics' => [Endpoint::AreaDemographics, fn () => Housepedia::areas()->withDistrict('WK0363KH')->demographics(), DTO\Demographics::class],
    'surroundings' => [Endpoint::Surroundings, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->surroundings(), DTO\Surroundings::class],
    'environment' => [Endpoint::Environment, fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->environment(), DTO\Environment::class],
    'account' => [Endpoint::Account, fn () => Housepedia::account()->details(), DTO\Account::class],
    'account usage' => [Endpoint::AccountUsage, fn () => Housepedia::account()->usage(), DTO\Usage::class],
    'account requests' => [Endpoint::AccountRequests, fn () => Housepedia::account()->requests(), DTO\RequestLogEntry::class],
    'catalog' => [Endpoint::Catalog, fn () => Housepedia::catalog(), DTO\Catalog\Catalog::class],
]);

it('has a case for every endpoint in the catalog that the sdk calls', function () {
    $products = collect(jsonFixture('getCatalog')['products'])
        ->filter(fn (array $product) => $product['status'] === 'live')
        ->mapWithKeys(fn (array $product) => [$product['id'] => [$product['method'], $product['path']]]);

    foreach (Endpoint::cases() as $endpoint) {
        expect($products->get($endpoint->value))->toBe([$endpoint->methods()[0], $endpoint->path()], $endpoint->value)
            ->and(file_exists(__DIR__ . '/../../resources/fixtures/v5/' . $endpoint->fixture() . '.json'))->toBeTrue($endpoint->value);
    }

    expect($products->keys()->diff(array_column(Endpoint::cases(), 'value'))->values()->all())->toBe([]);
});

it('matches fixed paths before locators', function (string $method, string $path, ?Endpoint $expected) {
    expect(Endpoint::match($method, $path))->toBe($expected);
})->with([
    ['GET', '/v5/addresses/suggest', Endpoint::AddressSuggest],
    ['GET', '/v5/addresses/reverse', Endpoint::AddressReverse],
    ['GET', '/v5/addresses/1076LJ-12-2', Endpoint::Address],
    ['GET', '/v5/areas/lookup', Endpoint::AreaLookup],
    ['GET', '/v5/areas/municipality:GM0363', Endpoint::Area],
    ['GET', '/v5/areas/national/trends', Endpoint::AreaTrends],
    ['GET', '/prefix/v5/catalog', Endpoint::Catalog],
    ['HEAD', '/v5/properties/bag:1/transactions', Endpoint::Transactions],
    ['POST', '/v5/properties/bag:1/transactions', null],
    ['GET', '/v5/valuations/scenarios', null],
    ['GET', '/v5/unknown', null],
]);

it('answers the example list for all addresses', function () {
    Housepedia::fake();

    expect(Housepedia::addresses()->withPostcode('1076LJ', 12)->findAll())->toHaveCount(count(jsonFixture('getAddressAll')['data']));
});

it('reads every page of the request log once', function () {
    Housepedia::fake();

    expect(Housepedia::account()->lazy()->requests()->count())->toBe(count(jsonFixture('getAccountRequests')['data']));

    Housepedia::assertSentCount(1, Endpoint::AccountRequests);
});

it('merges an array into the example response', function () {
    Housepedia::fake([
        Endpoint::Valuation->value => ['valuation' => ['value' => 450000]],
        Endpoint::AddressSuggest->value => ['data' => [['type' => 'address', 'label' => 'Amazonenstraat 12-2, 1076LJ Amsterdam']]],
    ]);

    $valuation = Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation();
    $example = jsonFixture('getValuation');

    expect($valuation->valuation()->value())->toBe(450000)
        ->and($valuation->valuation()->lower())->toBe($example['valuation']['lower'])
        ->and($valuation->confidence()?->class())->toBe($example['confidence']['class'])
        ->and(Housepedia::addresses()->suggest('1076LJ')->map->label()->all())->toBe(['Amazonenstraat 12-2, 1076LJ Amsterdam']);
});

it('answers a json response as given', function () {
    Housepedia::fake()->respond(Endpoint::AddressSuggest, FakeResponse::json(['data' => []]));

    $suggestions = Housepedia::addresses()->suggest('1076LJ');

    expect($suggestions)->toBeEmpty()
        ->and($suggestions->meta())->toBeNull();
});

it('throws the exception of an error response', function () {
    Housepedia::fake([Endpoint::Valuation->value => FakeResponse::error(404, 'not_found', 'No home at this address.')]);

    try {
        Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation();
    } catch (NotFoundException $exception) {
        expect($exception->status())->toBe(404)
            ->and($exception->errorCode())->toBe('not_found')
            ->and($exception->title())->toBe('Not Found')
            ->and($exception->detail())->toBe('No home at this address.');

        return;
    }

    $this->fail('No NotFoundException was thrown.');
});

it('throws an ambiguous address with its candidates', function () {
    Housepedia::fake([Endpoint::Address->value => FakeResponse::error(409, 'address_ambiguous', body: [
        'errors' => [jsonFixture('getAddress')],
    ])]);

    try {
        Housepedia::addresses()->withPostcode('1076LJ', 12)->find();
    } catch (AddressAmbiguousException $exception) {
        expect($exception->candidates())->toHaveCount(1);

        return;
    }

    $this->fail('No AddressAmbiguousException was thrown.');
});

it('creates an error without a code or detail', function () {
    $response = FakeResponse::error(599, headers: ['Retry-After' => '3']);

    expect($response->body())->toBe(['status' => 599, 'title' => 'HTTP 599'])
        ->and($response->status())->toBe(599)
        ->and($response->headers())->toBe(['Retry-After' => '3'])
        ->and($response->toPsrResponse()->getHeaderLine('Content-Type'))->toBe('application/problem+json');
});

it('answers the responses of an endpoint in order and repeats the last', function () {
    Sleep::fake();

    Housepedia::fake()->respond(
        Endpoint::Valuation,
        FakeResponse::error(429, 'rate_limited', headers: ['Retry-After' => '1']),
        ['valuation' => ['value' => 1]],
        ['valuation' => ['value' => 2]],
    );

    $home = Housepedia::properties()->withPostcode('1076LJ', 12, '2');

    expect($home->valuation()->valuation()->value())->toBe(1)
        ->and($home->valuation()->valuation()->value())->toBe(2)
        ->and($home->valuation()->valuation()->value())->toBe(2);

    Housepedia::assertSentCount(4, Endpoint::Valuation);
});

it('throws the rate limit when every retry is rate limited', function () {
    Sleep::fake();

    Housepedia::fake([Endpoint::Valuation->value => FakeResponse::error(429)]);

    expect(fn () => Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation())->toThrow(RateLimitException::class);

    Housepedia::assertSentCount(3);
});

it('answers with a closure', function () {
    Housepedia::fake([
        Endpoint::AddressSuggest->value => fn (RecordedRequest $request) => ['data' => [['type' => 'address', 'label' => $request->query('q')]]],
        Endpoint::Valuation->value => fn () => FakeResponse::error(422, 'data_unavailable'),
    ]);

    expect(Housepedia::addresses()->suggest('Amazonenstraat')->first()?->label())->toBe('Amazonenstraat')
        ->and(fn () => Housepedia::properties()->withBagId('1')->valuation())->toThrow(\Henzeb\Housepedia\Data\Intelligence\Exceptions\DataUnavailableException::class);
});

it('rejects a closure that returns no response', function () {
    Housepedia::fake([Endpoint::Catalog->value => fn () => 'nothing']);

    Housepedia::catalog();
})->throws(UnexpectedValueException::class, 'A Housepedia fake response closure must return an array or a FakeResponse.');

it('answers a head request without a body', function () {
    $fake = Housepedia::fake();
    $home = Housepedia::properties()->withBagId('0363200000028422');

    expect($home->hasTransactions())->toBeTrue();

    $fake->respond(Endpoint::Transactions, FakeResponse::error(404));

    expect($home->hasTransactions())->toBeFalse()
        ->and((string)$fake->sendRequest(new PsrRequest('HEAD', 'https://huispedia.dev/v5/properties/bag:1/transactions'))->getBody())->toBe('');

    Housepedia::assertSent(Endpoint::Transactions, fn (RecordedRequest $request) => $request->method() === 'HEAD');
});

it('throws for a request that matches no endpoint', function () {
    $fake = Housepedia::fake();

    try {
        Housepedia::get('/v5/unknown');
    } catch (StrayRequestException $exception) {
        expect($exception->getMessage())->toBe('No Housepedia endpoint matches GET /v5/unknown.')
            ->and($exception->request()->getUri()->getPath())->toBe('/v5/unknown')
            ->and($fake->recorded())->toBeEmpty();

        return;
    }

    $this->fail('No StrayRequestException was thrown.');
});

it('rejects an unknown endpoint key', function () {
    Housepedia::fake(['unknown' => []]);
})->throws(ValueError::class);

it('answers raw requests to a known endpoint', function () {
    Housepedia::fake([Endpoint::Account->value => ['name' => 'Housepedia']]);

    expect(Housepedia::get('/v5/account')->collect()->get('name'))->toBe('Housepedia');

    Housepedia::assertSent(Endpoint::Account);
});

it('records the requests', function () {
    $fake = Housepedia::fake();

    Housepedia::addresses()->suggest('1076LJ', 5);
    Housepedia::valuations()->scenarios([new ScenarioRow(Locator::bagId('0363200000028422'))]);

    $suggest = $fake->recorded(Endpoint::AddressSuggest)->sole();
    $scenarios = Housepedia::recorded(Endpoint::ValuationScenarios)->sole();

    expect($suggest->endpoint())->toBe(Endpoint::AddressSuggest)
        ->and($suggest->method())->toBe('GET')
        ->and($suggest->url())->toBe('https://huispedia.dev/v5/addresses/suggest?q=1076LJ&limit=5')
        ->and($suggest->path())->toBe('/v5/addresses/suggest')
        ->and($suggest->query())->toBe(['q' => '1076LJ', 'limit' => '5'])
        ->and($suggest->query('limit'))->toBe('5')
        ->and($suggest->query('types'))->toBeNull()
        ->and($suggest->body())->toBe([])
        ->and($suggest->header('Authorization'))->toBe('Bearer hp_laravel')
        ->and($suggest->header('X-Unknown'))->toBeNull()
        ->and($suggest->toPsrRequest()->getMethod())->toBe('GET')
        ->and($scenarios->body())->toBe(['data' => [['locator' => 'bag:0363200000028422', 'scenarios' => []]]])
        ->and($fake->recorded(callback: fn (RecordedRequest $request) => $request->method() === 'POST'))->toHaveCount(1)
        ->and($fake->recorded())->toHaveCount(2);
});

it('passes the assertions', function () {
    $fake = Housepedia::fake();

    $fake->assertNothingSent();
    Housepedia::assertNothingSent();

    Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation();

    Housepedia::assertSent(Endpoint::Valuation);
    Housepedia::assertSent(Endpoint::Valuation, fn (RecordedRequest $request) => str_contains($request->path(), '1076LJ-12-2'));
    Housepedia::assertNotSent(Endpoint::PropertyWoz);
    Housepedia::assertNotSent(Endpoint::Valuation, fn (RecordedRequest $request) => str_contains($request->path(), '9999XX'));
    Housepedia::assertSentCount(1);
    Housepedia::assertSentCount(1, Endpoint::Valuation);
    Housepedia::assertSentCount(0, Endpoint::PropertyWoz);
});

it('fails the assertions', function (Closure $assertion, string $message) {
    Housepedia::fake();

    Housepedia::properties()->withPostcode('1076LJ', 12, '2')->valuation();

    expect($assertion)->toThrow(AssertionFailedError::class, $message);
})->with([
    'sent' => [fn () => Housepedia::assertSent(Endpoint::PropertyWoz), 'An expected request to the Housepedia endpoint [property_woz] was not sent.'],
    'sent with callback' => [fn () => Housepedia::assertSent(Endpoint::Valuation, fn () => false), 'An expected request to the Housepedia endpoint [valuation] was not sent.'],
    'not sent' => [fn () => Housepedia::assertNotSent(Endpoint::Valuation), 'An unexpected request to the Housepedia endpoint [valuation] was sent.'],
    'count' => [fn () => Housepedia::assertSentCount(2), 'The number of requests sent to Housepedia does not match.'],
    'count of endpoint' => [fn () => Housepedia::assertSentCount(2, Endpoint::Valuation), 'The number of requests sent to the Housepedia endpoint [valuation] does not match.'],
    'nothing sent' => [fn () => Housepedia::assertNothingSent(), 'Requests were sent to Housepedia.'],
]);

it('requires a fake for the facade assertions', function () {
    Housepedia::assertNothingSent();
})->throws(RuntimeException::class, 'Call Housepedia::fake() before asserting Housepedia requests.');

it('replaces clients that were resolved before the fake', function () {
    $before = app(Client::class);

    $fake = Housepedia::fake();

    expect(app(HousepediaServiceProvider::HTTP_CLIENT))->toBe($fake)
        ->and(app(Client::class))->not->toBe($before)
        ->and(app(CoreClient::class)->catalog())->toBeInstanceOf(DTO\Catalog\Catalog::class)
        ->and(app(Client::class)->catalog())->toBeInstanceOf(DTO\Catalog\Catalog::class);

    Housepedia::assertSentCount(2, Endpoint::Catalog);
});

it('bypasses the laravel http client', function () {
    Http::fake();
    Http::preventStrayRequests();

    Housepedia::fake();

    Housepedia::catalog();

    Http::assertNothingSent();
    expect(app(HousepediaServiceProvider::HTTP_CLIENT))->toBeInstanceOf(HousepediaFake::class);
});
