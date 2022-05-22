<?php


namespace Corma\Test\Repository;

use Corma\Exception\ClassNotFoundException;
use Corma\ObjectMapper;
use Corma\Repository\ObjectRepository;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\LimitedArrayCache;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ObjectRepositoryFactoryTest extends TestCase
{
    private ObjectRepositoryFactoryInterface $repositoryFactory;

    public function testGetRepositoryFullClass()
    {
        $repository = $this->repositoryFactory->getRepository(ExtendedDataObject::class);
        $this->assertInstanceOf(ExtendedDataObjectRepository::class, $repository);
    }

    public function testWithContainer()
    {
        /** @var ContainerInterface|MockObject $container */
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())->method('has')->with(ExtendedDataObjectRepository::class)->willReturn(true);
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)->disableOriginalConstructor()->getMock();
        $container->expects($this->once())->method('get')->with(ExtendedDataObjectRepository::class)->willReturn($mockRepo);
        $this->repositoryFactory->setContainer($container);
        $repository = $this->repositoryFactory->getRepository(ExtendedDataObject::class);
        $this->assertInstanceOf(ExtendedDataObjectRepository::class, $repository);
    }

    public function testGetRepositoryCaching()
    {
        $repository = $this->repositoryFactory->getRepository(ExtendedDataObject::class);
        $repository2 = $this->repositoryFactory->getRepository(ExtendedDataObject::class);
        $this->assertTrue($repository === $repository2);
    }

    public function testGetDefaultRepository()
    {
        $repository = $this->repositoryFactory->getRepository(OtherDataObject::class);

        $this->assertEquals(ObjectRepository::class, get_class($repository));
        $this->assertEquals(OtherDataObject::class, $repository->getClassName());
    }

    public function testGetDefaultRepositoryWithContainer()
    {
        /** @var ContainerInterface|MockObject $container */
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())->method('has')->with(ObjectRepository::class)->willReturn(false);
        $this->repositoryFactory->setContainer($container);
        $repository = $this->repositoryFactory->getRepository(OtherDataObject::class);
        $this->assertEquals(ObjectRepository::class, get_class($repository));
        $this->assertEquals(OtherDataObject::class, $repository->getClassName());
    }

    public function testClassNotFound()
    {
        $this->expectException(ClassNotFoundException::class);
        $this->repositoryFactory->getRepository('Nope');
    }

    public function setUp(): void
    {
        /** @var Connection $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dispatcher = new EventDispatcher();

        $this->repositoryFactory = new ObjectRepositoryFactory();
        $this->repositoryFactory->setDependencies([$connection, $objectMapper, new LimitedArrayCache(), $dispatcher]);
    }
}
