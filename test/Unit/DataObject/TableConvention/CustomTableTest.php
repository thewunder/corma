<?php

namespace Corma\Test\Unit\DataObject\TableConvention;

use Corma\DataObject\TableConvention\CustomTable;
use Corma\Test\Fixtures\ExtendedDataObject;
use PHPUnit\Framework\TestCase;

class CustomTableTest extends TestCase
{
    public function testCustomTable(): void
    {
        $customTable = new CustomTable('custom_table');
        $this->assertEquals('custom_table', $customTable->getTable(ExtendedDataObject::class));
    }
}
