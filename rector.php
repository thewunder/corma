<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ])->withPhpSets()
    ->withSets([
        PHPUnitSetList::PHPUNIT_100
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
    ]);
