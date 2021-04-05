<?php
namespace Corma\Test\DataObject\TableConvention;

use Corma\DataObject\TableConvention\AnnotationCustomizableTableConvention;
use Corma\Test\Fixtures\AnnotatedDataObject;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\Inflector;
use Minime\Annotations\Reader;
use PHPUnit\Framework\TestCase;

class AnnotationCustomizableTableConventionTest extends TestCase
{
    public function testGetTableWithAnnotation()
    {
        $convention = new AnnotationCustomizableTableConvention(Inflector::build(), Reader::createFromDefaults());
        $this->assertEquals('custom_table', $convention->getTable(AnnotatedDataObject::class));
    }

    public function testGetTableWithOutAnnotation()
    {
        $convention = new AnnotationCustomizableTableConvention(Inflector::build(), Reader::createFromDefaults());
        $this->assertEquals('extended_data_objects', $convention->getTable(ExtendedDataObject::class));
    }
}
