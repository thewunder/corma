<?php
namespace Corma\Test\DataObject\Factory;

use Corma\DataObject\Factory\PsrContainerObjectFactory;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\ObjectWithDependencies;
use Corma\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PsrContainerObjectFactoryTest extends TestCase
{
    private ContainerInterface|MockObject $container;
    private ObjectHydratorInterface|MockObject $hydrator;
    private PsrContainerObjectFactory $factory;

    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $this->hydrator = $this->getMockBuilder(ObjectHydratorInterface::class)->getMock();
        $this->factory = new PsrContainerObjectFactory($this->container, $this->hydrator);
    }

    public function testCreate(): void
    {
        $object = new ExtendedDataObject();
        $this->container->expects($this->once())->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->once())->method('get')->with(ExtendedDataObject::class)->willReturn($object);
        $this->assertEquals($object, $this->factory->create(ExtendedDataObject::class));
    }

    public function testCreateWithData(): void
    {
        $object = new ExtendedDataObject();
        $data = ['myColumn'=>'value'];
        $this->container->expects($this->once())->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->once())->method('get')->with(ExtendedDataObject::class)->willReturn($object);
        $this->hydrator->expects($this->once())->method('hydrate')->with($object, $data);
        $this->assertEquals($object, $this->factory->create(ExtendedDataObject::class, $data));
    }

    public function testCreateWithDependencies(): void
    {
        $data = ['myColumn'=>'value'];
        $dependencies = [new EventDispatcher()];
        $this->container->expects($this->never())->method('get');
        $this->hydrator->expects($this->once())->method('hydrate');
        $obj = $this->factory->create(ObjectWithDependencies::class, $data, $dependencies);
        $this->assertInstanceOf(ObjectWithDependencies::class, $obj);
    }

    public function testFetchAll(): void
    {
        $object = new ExtendedDataObject();
        $data = ['myColumn'=>'value'];
        $object2 = new ExtendedDataObject();
        $data2 = ['myColumn'=>'value2'];
        $this->container->expects($this->exactly(2))->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->exactly(2))->method('get')->with(ExtendedDataObject::class)->willReturn($object, $object2);
        $matcher = $this->exactly(2);
        $this->hydrator->expects($matcher)->method('hydrate')->willReturnCallback(fn() => match ($matcher->numberOfInvocations()) {
            1 => [$object, $data],
            2 => [$object2, $data2],
        });

        /** @var Result | MockObject $mockResult */
        $mockResult = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();
        $mockResult->expects($this->exactly(3))->method('fetchAssociative')->willReturn($data, $data2, false);

        $this->assertCount(2, $this->factory->fetchAll(ExtendedDataObject::class, $mockResult));
    }

    public function testFetchOne(): void
    {
        $object = new ExtendedDataObject();
        $data = ['myColumn'=>'value'];
        $this->container->expects($this->once())->method('has')->with(ExtendedDataObject::class)->willReturn(true);
        $this->container->expects($this->once())->method('get')->with(ExtendedDataObject::class)->willReturn($object);
        $this->hydrator->expects($this->once())->method('hydrate')->with($object, $data);

        /** @var Result | MockObject $mockResult */
        $mockResult = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();
        $mockResult->expects($this->once())->method('fetchAssociative')->willReturn($data);

        $this->assertEquals($object, $this->factory->fetchOne(ExtendedDataObject::class, $mockResult));
    }
}
