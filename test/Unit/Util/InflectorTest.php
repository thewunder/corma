<?php

namespace Corma\Test\Unit\Util;

use Corma\Util\Inflector;
use PHPUnit\Framework\TestCase;

class InflectorTest extends TestCase
{
    private Inflector $inflector;
    public function setUp(): void
    {
        $this->inflector = Inflector::build();
    }

    public function testMethodNameFromColumn()
    {
        $this->assertEquals('Person', $this->inflector->methodNameFromColumn('personId'));
    }

    public function testMethodNameFromColumnPlural()
    {
        $this->assertEquals('Dogs', $this->inflector->methodNameFromColumn('dog', true));
    }

    public function testChildrenPlural()
    {
        $this->assertEquals('children', $this->inflector->pluralize('children'));
    }

    public function testGetterFromColumn()
    {
        $this->assertEquals('getManagedByUser', $this->inflector->getterFromColumn('managedByUser'));
    }

    public function testAlias()
    {
        $this->assertEquals('mp', $this->inflector->aliasFromProperty('myProperty'));
    }
}
