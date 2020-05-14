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
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ObjectRepositoryFactoryTest extends TestCase
{
    /** @var ObjectRepositoryFactoryInterface */
    private $repositoryFactory;

    public function testGetRepositoryFullClass()
    {
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

        $this->repositoryFactory = new ObjectRepositoryFactory([$connection, $objectMapper, new ArrayCache(), $dispatcher]);
    }
}
