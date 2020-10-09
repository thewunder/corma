<?php
namespace Corma\Test\DataObject\Factory;

use Corma\DataObject\Factory\PsrContainerObjectFactory;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\ObjectWithDependencies;
use Doctrine\DBAL\Driver\ResultStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PsrContainerObjectFactoryTest extends TestCase
{
    /** @var MockObject | ContainerInterface */
    private $container;
    /** @var MockObject | ObjectHydratorInterface */
    private $hydrator;
    /** @var PsrContainerObjectFactory */
    private $factory;

    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $this->hydrator = $this->getMockBuilder(ObjectHydratorInterface::class)->getMock();
        $this->factory = new PsrContainerObjectFactory($this->container, $this->hydrator);
    }

    public function testCreate()
    {
        $object = new ExtendedDataObject();
        $this->container->expects($this->once())->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->once())->method('get')->with(ExtendedDataObject::class)->willReturn($object);
        $this->assertEquals($object, $this->factory->create(ExtendedDataObject::class));
    }

    public function testCreateWithData()
    {
        $object = new ExtendedDataObject();
        $data = ['myColumn'=>'value'];
        $this->container->expects($this->once())->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->once())->method('get')->with(ExtendedDataObject::class)->willReturn($object);
        $this->hydrator->expects($this->once())->method('hydrate')->with($object, $data);
        $this->assertEquals($object, $this->factory->create(ExtendedDataObject::class, $data));
    }

    public function testCreateWithDependencies()
    {
        $data = ['myColumn'=>'value'];
        $dependencies = [new EventDispatcher()];
        $this->container->expects($this->never())->method('get');
        $this->hydrator->expects($this->once())->method('hydrate');
        $obj = $this->factory->create(ObjectWithDependencies::class, $data, $dependencies);
        $this->assertInstanceOf(ObjectWithDependencies::class, $obj);
    }

    public function testFetchAll()
    {
        $object = new ExtendedDataObject();
        $data = ['myColumn'=>'value'];
        $object2 = new ExtendedDataObject();
        $data2 = ['myColumn'=>'value2'];
        $this->container->expects($this->exactly(2))->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->exactly(2))->method('get')->with(ExtendedDataObject::class)->willReturn($object, $object2);
        $this->hydrator->expects($this->exactly(2))->method('hydrate')->withConsecutive([$object, $data], [$object2, $data2]);

        /** @var ResultStatement | MockObject $mockResult */
        $mockResult = $this->getMockBuilder(ResultStatement::class)->getMock();
        $mockResult->expects($this->exactly(3))->method('fetch')->willReturn($data, $data2, false);

        $this->assertCount(2, $this->factory->fetchAll(ExtendedDataObject::class, $mockResult));
    }

    public function testFetchOne()
    {
        $object = new ExtendedDataObject();
        $data = ['myColumn'=>'value'];
        $this->container->expects($this->once())->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->once())->method('get')->with(ExtendedDataObject::class)->willReturn($object);
        $this->hydrator->expects($this->once())->method('hydrate')->with($object, $data);

        /** @var ResultStatement | MockObject $mockResult */
        $mockResult = $this->getMockBuilder(ResultStatement::class)->getMock();
        $mockResult->expects($this->once())->method('fetch')->willReturn($data);

        $this->assertEquals($object, $this->factory->fetchOne(ExtendedDataObject::class, $mockResult));
    }
}