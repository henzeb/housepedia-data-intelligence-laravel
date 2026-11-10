<?php

/**
 * @return list<ReflectionClass<object>>
 */
function sourceClasses(): array
{
    $source = dirname(__DIR__, 2) . '/src/';
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
        $name = 'Henzeb\\Housepedia\\Data\\Intelligence\\Laravel\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($source)));
        $class = new ReflectionClass($name);

        if (! $class->isInterface() && ! $class->isTrait() && ! $class->isEnum()) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('declares no final classes', function () {
    foreach (sourceClasses() as $class) {
        expect($class->isFinal())->toBeFalse($class->getName());
    }
});

it('declares no readonly classes', function () {
    foreach (sourceClasses() as $class) {
        expect($class->isReadOnly())->toBeFalse($class->getName());
    }
});

it('declares no readonly properties', function () {
    foreach (sourceClasses() as $class) {
        foreach ($class->getProperties() as $property) {
            expect($property->isReadOnly())->toBeFalse($class->getName() . '::$' . $property->getName());
        }
    }
});
