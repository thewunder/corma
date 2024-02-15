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
use PHPUnit\Framework\TestCase;

class AutoIncrementIdentifierTest extends TestCase
{
    public function testGetIdColumn(): void
    {
        $object = new ExtendedDataObject();
        $this->assertEquals('id', $this->getIdentifier()->getIdColumn($object));
    }

    public function testAnnotationColumn(): void
    {
        $object = new AnnotatedDataObject();
        $this->assertEquals('custom_id', $this->getIdentifier()->getIdColumn($object));
    }

    public function testGetId(): void
    {
        $object = new ExtendedDataObject();
        $object->setId(99);
        $this->assertEquals(99, $this->getIdentifier()->getId($object));
    }

    public function testIsNew(): void
    {
        $object = new ExtendedDataObject();
        $this->assertTrue($this->getIdentifier()->isNew($object));
        $object->setId(99);
        $this->assertFalse($this->getIdentifier()->isNew($object));
    }

    public function testMissingGetter(): void
    {
        $this->expectException(MethodNotImplementedException::class);
        $object = new MissingIdGettersAndSetters();
        $this->getIdentifier()->getId($object);
    }

    public function testGetIdWithAnnotation(): void
    {
        $object = new AnnotatedDataObject();
        $object->setCustomId(99);
        $this->assertEquals(99, $this->getIdentifier()->getId($object));
    }

    public function testSetId(): void
    {
        $object = new ExtendedDataObject();
        $this->getIdentifier()->setId($object, 99);
        $this->assertEquals(99, $object->getId());
    }

    public function testMissingSetter(): void
    {
        $this->expectException(MethodNotImplementedException::class);
        $object = new MissingIdGettersAndSetters();
        $this->getIdentifier()->setId($object, 99);
    }

    public function testGetIds(): void
    {
        $objects = [];
        $objects[] = (new ExtendedDataObject())->setId(42);
        $objects[] = (new ExtendedDataObject())->setId(43);
        $this->assertEquals([42, 43], $this->getIdentifier()->getIds($objects));
    }

    public function testGetIdsMissingGetter(): void
    {
        $this->expectException(MethodNotImplementedException::class);
        $object = new MissingIdGettersAndSetters();
        $this->getIdentifier()->getIds([$object]);
    }

    public function testSetNewId(): void
    {
        $object = new ExtendedDataObject();
        $this->getIdentifier()->setNewId($object);
        $this->assertEquals(42, $object->getId());
    }

    protected function getIdentifier(): CustomizableAutoIncrementIdentifier
    {
        $queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()->onlyMethods(['getLastInsertId'])
            ->getMock();
        $queryHelper->method('getLastInsertId')->willReturn('42');
        $inflector = Inflector::build();
        return new CustomizableAutoIncrementIdentifier($inflector, $queryHelper, new DefaultTableConvention($inflector));
    }
}
