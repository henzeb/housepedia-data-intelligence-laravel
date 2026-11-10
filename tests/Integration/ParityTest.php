<?php

use Henzeb\Housepedia\Data\Intelligence\Client as CoreClient;
use Henzeb\Housepedia\Data\Intelligence\Contracts as Core;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\Results as CoreResults;
use Henzeb\Housepedia\Data\Intelligence\Contracts\DTO\ReverseResults as CoreReverseResults;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Client;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Resources;
use Henzeb\Housepedia\Data\Intelligence\Laravel\Results;
use Henzeb\Housepedia\Data\Intelligence\Laravel\ReverseResults;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * @return array<string, ReflectionMethod>
 */
function publicMethods(string $class): array
{
    $methods = [];

    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!$method->isConstructor() && !$method->isStatic()) {
            $methods[$method->getName()] = $method;
        }
    }

    ksort($methods);

    return $methods;
}

it('exposes the same fluent api as the core sdk', function (string $core, string $laravel, array $extra) {
    $coreMethods = publicMethods($core);
    $laravelMethods = publicMethods($laravel);

    expect(array_values(array_diff(array_keys($laravelMethods), $extra)))->toBe(array_keys($coreMethods));

    foreach ($coreMethods as $name => $method) {
        $parameters = fn (ReflectionMethod $method) => array_map(
            fn (ReflectionParameter $parameter) => [$parameter->getName(), (string)$parameter->getType(), $parameter->isOptional()],
            $method->getParameters(),
        );

        $laravelParameters = $parameters($laravelMethods[$name]);
        $coreParameters = $parameters($method);

        if ($laravel === Resources\Valuations::class && $name === 'scenarios') {
            $coreParameters[0][1] = 'iterable';
        }

        expect($laravelParameters)->toBe($coreParameters, $laravel . '::' . $name);
    }
})->with([
    [CoreClient::class, Client::class, ['core']],
    [Core\Addresses::class, Resources\Addresses::class, []],
    [Core\Properties::class, Resources\Properties::class, []],
    [Core\Buildings::class, Resources\Buildings::class, []],
    [Core\Areas::class, Resources\Areas::class, []],
    [Core\PostcodeArea::class, Resources\PostcodeArea::class, []],
    [Core\Valuations::class, Resources\Valuations::class, []],
    [Core\Account::class, Resources\Account::class, []],
    [Core\LazyAccount::class, Resources\LazyAccount::class, []],
]);

it('implements the core interface', function (string $core, string $laravel) {
    expect(is_subclass_of($laravel, $core))->toBeTrue($laravel . ' implements ' . $core);
})->with([
    [Core\Addresses::class, Resources\Addresses::class],
    [Core\Properties::class, Resources\Properties::class],
    [Core\Buildings::class, Resources\Buildings::class],
    [Core\Areas::class, Resources\Areas::class],
    [Core\PostcodeArea::class, Resources\PostcodeArea::class],
    [Core\Valuations::class, Resources\Valuations::class],
    [Core\Account::class, Resources\Account::class],
    [Core\LazyAccount::class, Resources\LazyAccount::class],
    [CoreResults::class, Results::class],
    [CoreReverseResults::class, ReverseResults::class],
]);

it('returns collections where the core returns lists and results', function (string $core, string $laravel) {
    foreach (publicMethods($core) as $name => $method) {
        $coreType = (string)$method->getReturnType();
        $laravelType = (string)publicMethods($laravel)[$name]->getReturnType();

        match (true) {
            $coreType === 'array' => expect($laravelType)->toBe(Collection::class, $laravel . '::' . $name),
            $coreType === CoreResults::class => expect($laravelType)->toBe(Results::class, $laravel . '::' . $name),
            $coreType === CoreReverseResults::class => expect($laravelType)->toBe(ReverseResults::class, $laravel . '::' . $name),
            $coreType === 'iterable' => expect($laravelType)->toBe(LazyCollection::class, $laravel . '::' . $name),
            $coreType === 'static' => expect($laravelType)->toBe('static'),
            str_starts_with(ltrim($coreType, '?'), Core::class . '\\DTO\\') => expect($laravelType)->toBe($coreType, $laravel . '::' . $name),
            str_starts_with($coreType, Core::class . '\\') => expect($laravelType)
                ->toBe(Resources::class . substr($coreType, strlen(Core::class)), $laravel . '::' . $name),
            default => expect($laravelType)->toBe($coreType, $laravel . '::' . $name),
        };
    }
})->with([
    [Core\Addresses::class, Resources\Addresses::class],
    [Core\Properties::class, Resources\Properties::class],
    [Core\Buildings::class, Resources\Buildings::class],
    [Core\Areas::class, Resources\Areas::class],
    [Core\PostcodeArea::class, Resources\PostcodeArea::class],
    [Core\Valuations::class, Resources\Valuations::class],
    [Core\Account::class, Resources\Account::class],
    [Core\LazyAccount::class, Resources\LazyAccount::class],
]);

it('gives the results the getters of the core results', function (string $core, string $laravel) {
    $getters = fn (string $class) => array_values(array_filter(
        array_keys(publicMethods($class)),
        fn (string $name) => !in_array($name, ['getIterator', 'count'], true) && !method_exists(Collection::class, $name),
    ));

    expect(array_map(fn (string $name) => (string)publicMethods($laravel)[$name]->getReturnType(), $getters($core)))
        ->toBe(array_map(fn (string $name) => (string)publicMethods($core)[$name]->getReturnType(), $getters($core)))
        ->and(is_subclass_of($laravel, Collection::class))->toBeTrue();
})->with([
    [CoreResults::class, Results::class],
    [CoreReverseResults::class, ReverseResults::class],
]);
