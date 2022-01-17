<?php
namespace Corma\Test\DataObject\TableConvention;

use Corma\DataObject\TableConvention\CustomizableTableConvention;
use Corma\Test\Fixtures\AnnotatedDataObject;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\Inflector;
use PHPUnit\Framework\TestCase;

class CustomizableTableConventionTest extends TestCase
{
    public function testGetTableWithAnnotation()
    {
        $convention = new CustomizableTableConvention(Inflector::build());
        $this->assertEquals('custom_table', $convention->getTable(AnnotatedDataObject::class));
    }

    public function testGetTableWithOutAnnotation()
    {
        $convention = new CustomizableTableConvention(Inflector::build());
        $this->assertEquals('extended_data_objects', $convention->getTable(ExtendedDataObject::class));
    }
}
