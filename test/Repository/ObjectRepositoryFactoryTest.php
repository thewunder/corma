<?php


namespace Corma\Test\Repository;


use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\QueryHelper;
use Corma\Util\QueryHelperInterface;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ObjectRepositoryFactoryTest extends \PHPUnit_Framework_TestCase
{
    /** @var ObjectRepositoryFactoryInterface */
    private $repositoryFactory;

    public function testGetRepository()
    {
        $repository = $this->repositoryFactory->getRepository('ExtendedDataObject');
        $this->assertInstanceOf(ExtendedDataObjectRepository::class, $repository);
    }

    public function testGetRepositoryFullClass()
    {
        $repository = $this->repositoryFactory->getRepository(ExtendedDataObject::class);
        $this->assertInstanceOf(ExtendedDataObjectRepository::class, $repository);
    }

    public function testGetRepositoryCaching()
    {
        $repository = $this->repositoryFactory->getRepository('ExtendedDataObject');
        $repository2 = $this->repositoryFactory->getRepository('ExtendedDataObject');
        $this->assertTrue($repository === $repository2);
    }

    /**
     * @expectedException \Corma\Exception\ClassNotFoundException
     */
    public function testClassNotFound()
    {
        $this->repositoryFactory->getRepository('Nope');
    }

    /**
     * @expectedException \Corma\Exception\InvalidClassException
     */
    public function testInvalidClass()
    {
        $this->repositoryFactory->getRepository('Invalid');
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testNoNamespaces()
    {
        $this->repositoryFactory = new ObjectRepositoryFactory([], []);
    }

    public function setUp()
    {
        /** @var Connection $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var QueryHelperInterface $queryHelper */
        $queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dispatcher = new EventDispatcher();

        $this->repositoryFactory = new ObjectRepositoryFactory(['Corma\\Test\\Fixtures'], [$connection, $dispatcher, $queryHelper, new ArrayCache()]);
    }
}
