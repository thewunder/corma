<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ])->withPhpSets()
    ->withSets([
        Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_100
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ]);
