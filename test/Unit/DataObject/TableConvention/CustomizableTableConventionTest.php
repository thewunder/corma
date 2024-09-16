<?php
namespace Corma\Test\Unit\DataObject\TableConvention;

use Corma\DataObject\TableConvention\CustomizableTableConvention;
use Corma\Test\Fixtures\AnnotatedDataObject;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\Inflector;
use PHPUnit\Framework\TestCase;

class CustomizableTableConventionTest extends TestCase
{
    public function testGetTableWithAnnotation(): void
    {
        $convention = new CustomizableTableConvention(Inflector::build());
        $this->assertEquals('custom_table', $convention->getTable(AnnotatedDataObject::class));
    }

    public function testGetTableWithOutAnnotation(): void
    {
        $convention = new CustomizableTableConvention(Inflector::build());
        $this->assertEquals('extended_data_objects', $convention->getTable(ExtendedDataObject::class));
    }
}
