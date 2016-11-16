<?php
namespace Corma\Test\DataObject\Factory;

use Corma\DataObject\Factory\PdoObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\ObjectWithDependencies;
use Doctrine\DBAL\Statement;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PdoObjectFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $factory = $this->getFactory();
        $object = $factory->create(ExtendedDataObject::class);
        $this->assertInstanceOf(ExtendedDataObject::class, $object);
    }

    public function testCreateWithData()
    {
        $factory = $this->getFactory();
        /** @var ExtendedDataObject $object */
        $object = $factory->create(ExtendedDataObject::class, [], ['myColumn'=>'asdf']);
        $this->assertEquals('asdf', $object->getMyColumn());
    }

    public function testCreateWithDependencies()
    {
        $factory = $this->getFactory();
        $object = $factory->create(ObjectWithDependencies::class, [new EventDispatcher()]);
        $this->assertInstanceOf(ObjectWithDependencies::class, $object);
    }

    public function testFetchOne()
    {
        $statement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()
            ->setMethods(['setFetchMode', 'fetch'])->getMock();

        $object = new ExtendedDataObject();
        $statement->expects($this->once())->method('setFetchMode');
        $statement->expects($this->once())->method('fetch')->willReturn($object);

        $factory = $this->getFactory();
        $result = $factory->fetchOne(ExtendedDataObject::class, $statement);
        $this->assertEquals($object, $result);
    }

    public function testFetchAll()
    {
        $statement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()
            ->setMethods(['fetchAll'])->getMock();

        $objects = [];
        $objects[] = new ExtendedDataObject();
        $objects[] = new ExtendedDataObject();
        $statement->expects($this->once())->method('fetchAll')->willReturn($objects);

        $factory = $this->getFactory();
        $result = $factory->fetchAll(ExtendedDataObject::class, $statement);
        $this->assertEquals($objects, $result);
    }

    /**
     * @return PdoObjectFactory
     */
    protected function getFactory()
    {
        $factory = new PdoObjectFactory(new ClosureHydrator());
        return $factory;
    }
}