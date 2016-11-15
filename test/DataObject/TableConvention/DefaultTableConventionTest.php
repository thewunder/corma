<?php
namespace DataObject\TableConvention;

use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\Test\Fixtures\AnnotatedDataObject;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\Inflector;
use Minime\Annotations\Reader;

class DefaultTableConventionTest extends \PHPUnit_Framework_TestCase
{
    public function testGetTable()
    {
        $convention = new DefaultTableConvention(new Inflector(), Reader::createFromDefaults());
        $this->assertEquals('extended_data_objects', $convention->getTable(ExtendedDataObject::class));
    }

    public function testGetTableWithAnnotation()
    {
        $convention = new DefaultTableConvention(new Inflector(), Reader::createFromDefaults());
        $this->assertEquals('custom_table', $convention->getTable(AnnotatedDataObject::class));
    }
}