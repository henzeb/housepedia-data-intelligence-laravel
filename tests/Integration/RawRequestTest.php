<?php

use Henzeb\Housepedia\Data\Intelligence\Laravel\Facades\Housepedia;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Response;
use Illuminate\Support\Collection;

it('returns a laravel response for other endpoints', function () {
    $this->http->addResponse(jsonResponse(['data' => [['id' => 1], ['id' => 2]], 'meta' => ['request_id' => 'req_1']], 200, ['X-RateLimit-Remaining' => '3']));

    $response = Housepedia::get('/v5/properties/1076LJ-12-2/listing', ['include' => 'rd']);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->status())->toBe(200)
        ->and($response->header('X-RateLimit-Remaining'))->toBe('3')
        ->and($response->integer('data.1.id'))->toBe(2)
        ->and($response->meta()?->requestId())->toBe('req_1')
        ->and($response->collect('data'))->toBeInstanceOf(Collection::class)->toHaveCount(2)
        ->and($response->collect('missing'))->toBeEmpty()
        ->and($response->collect()->keys()->all())->toBe(['data', 'meta'])
        ->and(lastRequest()->getUri()->getQuery())->toBe('include=rd');
});

it('posts to other endpoints', function () {
    $this->http->addResponse(jsonResponse(['job_id' => 'job_1']));

    $response = Housepedia::post('/v5/batch/valuations', ['data' => []], ['dry' => true]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->string('job_id'))->toBe('job_1')
        ->and(lastRequest()->getMethod())->toBe('POST')
        ->and(lastRequest()->getUri()->getQuery())->toBe('dry=true')
        ->and((string)lastRequest()->getBody())->toBe('{"data":[]}');
});
