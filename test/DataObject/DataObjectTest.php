<?php
namespace Corma\Test\DataObject;


use Corma\Test\ExtendedDataObject;

class DataObjectTest extends \PHPUnit_Framework_TestCase
{
    public function testGetClassName()
    {
        $this->assertEquals('ExtendedDataObject', ExtendedDataObject::getClassName());
    }

    public function testGetTableName()
    {
        $this->assertEquals('extended_data_objects', ExtendedDataObject::getTableName());
    }
}
