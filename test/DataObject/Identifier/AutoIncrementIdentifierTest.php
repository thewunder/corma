<?php
namespace Corma\Test\DataObject\Identifier;

use Corma\DataObject\Identifier\CustomizableAutoIncrementIdentifier;
use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\Exception\MethodNotImplementedException;
use Corma\QueryHelper\QueryHelper;
use Corma\Test\Fixtures\AnnotatedDataObject;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\MissingIdGettersAndSetters;
use Corma\Util\Inflector;
use Minime\Annotations\Reader;
use PHPUnit\Framework\TestCase;

class AutoIncrementIdentifierTest extends TestCase
{
    public function testGetIdColumn()
    {
        $object = new ExtendedDataObject();
        $this->assertEquals('id', $this->getIdentifier()->getIdColumn($object));
    }

    public function testAnnotationColumn()
    {
        $object = new AnnotatedDataObject();
        $this->assertEquals('custom_id', $this->getIdentifier()->getIdColumn($object));
    }

    public function testGetId()
    {
        $object = new ExtendedDataObject();
        $object->setId(99);
        $this->assertEquals(99, $this->getIdentifier()->getId($object));
    }

    public function testIsNew()
    {
        $object = new ExtendedDataObject();
        $this->assertTrue($this->getIdentifier()->isNew($object));
        $object->setId(99);
        $this->assertFalse($this->getIdentifier()->isNew($object));
    }

    public function testMissingGetter()
    {
        $this->expectException(MethodNotImplementedException::class);
        $object = new MissingIdGettersAndSetters();
        $this->getIdentifier()->getId($object);
    }

    public function testGetIdWithAnnotation()
    {
        $object = new AnnotatedDataObject();
        $object->setCustomId(99);
        $this->assertEquals(99, $this->getIdentifier()->getId($object));
    }

    public function testSetId()
    {
        $object = new ExtendedDataObject();
        $this->getIdentifier()->setId($object, 99);
        $this->assertEquals(99, $object->getId());
    }

    public function testMissingSetter()
    {
        $this->expectException(MethodNotImplementedException::class);
        $object = new MissingIdGettersAndSetters();
        $this->getIdentifier()->setId($object, 99);
    }

    public function testGetIds()
    {
        $objects = [];
        $objects[] = (new ExtendedDataObject())->setId(42);
        $objects[] = (new ExtendedDataObject())->setId(43);
        $this->assertEquals([42, 43], $this->getIdentifier()->getIds($objects));
    }

    public function testGetIdsMissingGetter()
    {
        $this->expectException(MethodNotImplementedException::class);
        $object = new MissingIdGettersAndSetters();
        $this->getIdentifier()->getIds([$object]);
    }

    public function testSetNewId()
    {
        $object = new ExtendedDataObject();
        $this->getIdentifier()->setNewId($object);
        $this->assertEquals(42, $object->getId());
    }

    /**
     * @return CustomizableAutoIncrementIdentifier
     */
    protected function getIdentifier()
    {
        $queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()->onlyMethods(['getLastInsertId'])
            ->getMock();
        $queryHelper->method('getLastInsertId')->willReturn('42');
        $inflector = Inflector::build();
        $reader = Reader::createFromDefaults();
        return new CustomizableAutoIncrementIdentifier($inflector, $reader, $queryHelper, new DefaultTableConvention($inflector, $reader));
    }
}
