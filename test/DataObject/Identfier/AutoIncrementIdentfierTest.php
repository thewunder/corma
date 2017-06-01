<?php
namespace DataObject\Identfier;

use Corma\DataObject\Identifier\CustomizableAutoIncrementIdentifier;
use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\QueryHelper\QueryHelper;
use Corma\Test\Fixtures\AnnotatedDataObject;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\MissingIdGettersAndSetters;
use Corma\Util\Inflector;
use Minime\Annotations\Reader;

class AutoIncrementIdentfierTest extends \PHPUnit_Framework_TestCase
{
    public function testGetIdColumn()
    {
        $object = new ExtendedDataObject();
        $this->assertEquals('id', $this->getIdentfier()->getIdColumn($object));
    }

    public function testAnnotationColumn()
    {
        $object = new AnnotatedDataObject();
        $this->assertEquals('custom_id', $this->getIdentfier()->getIdColumn($object));
    }

    public function testGetId()
    {
        $object = new ExtendedDataObject();
        $object->setId(99);
        $this->assertEquals(99, $this->getIdentfier()->getId($object));
    }

    public function testIsNew()
    {
        $object = new ExtendedDataObject();
        $this->assertTrue($this->getIdentfier()->isNew($object));
        $object->setId(99);
        $this->assertFalse($this->getIdentfier()->isNew($object));
    }

    /**
     * @expectedException \Corma\Exception\MethodNotImplementedException
     */
    public function testMissingGetter()
    {
        $object = new MissingIdGettersAndSetters();
        $this->getIdentfier()->getId($object);
    }

    public function testGetIdWithAnnotation()
    {
        $object = new AnnotatedDataObject();
        $object->setCustomId(99);
        $this->assertEquals(99, $this->getIdentfier()->getId($object));
    }

    public function testSetId()
    {
        $object = new ExtendedDataObject();
        $this->getIdentfier()->setId($object, 99);
        $this->assertEquals(99, $object->getId());
    }

    /**
     * @expectedException \Corma\Exception\MethodNotImplementedException
     */
    public function testMissingSetter()
    {
        $object = new MissingIdGettersAndSetters();
        $this->getIdentfier()->setId($object, 99);
    }

    public function testGetIds()
    {
        $objects = [];
        $objects[] = (new ExtendedDataObject())->setId(42);
        $objects[] = (new ExtendedDataObject())->setId(43);
        $this->assertEquals([42, 43], $this->getIdentfier()->getIds($objects));
    }

    public function testSetNewId()
    {
        $object = new ExtendedDataObject();
        $this->getIdentfier()->setNewId($object);
        $this->assertEquals(42, $object->getId());
    }

    /**
     * @return CustomizableAutoIncrementIdentifier
     */
    protected function getIdentfier()
    {
        $queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()->setMethods(['getLastInsertId'])
            ->getMock();
        $queryHelper->method('getLastInsertId')->willReturn(42);
        $inflector = new Inflector();
        $reader = Reader::createFromDefaults();
        return new CustomizableAutoIncrementIdentifier($inflector, $reader, $queryHelper, new DefaultTableConvention($inflector, $reader));
    }
}
