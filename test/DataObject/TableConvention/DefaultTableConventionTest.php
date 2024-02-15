<?php
namespace DataObject\TableConvention;

use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\Inflector;
use PHPUnit\Framework\TestCase;

class DefaultTableConventionTest extends TestCase
{
    public function testGetTable(): void
    {
        $convention = new DefaultTableConvention(Inflector::build());
        $this->assertEquals('extended_data_objects', $convention->getTable(ExtendedDataObject::class));
    }
}
