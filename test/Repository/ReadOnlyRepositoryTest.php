<?php
namespace Corma\Test\Repository;

use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ReadOnlyRepository;
use Corma\QueryHelper\QueryHelper;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class ReadOnlyRepositoryTest extends TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $objectMapper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $connection;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $queryHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $cache;

    public function setUp()
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->cache = $this->getMockBuilder(ArrayCache::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @expectedException \Corma\Exception\BadMethodCallException
     */
    public function testSave()
    {
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->save($object);
    }

    /**
     * @expectedException \Corma\Exception\BadMethodCallException
     */
    public function testSaveAll()
    {
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->saveAll([$object]);
    }

    /**
     * @expectedException \Corma\Exception\BadMethodCallException
     */
    public function testDelete()
    {
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    /**
     * @expectedException \Corma\Exception\BadMethodCallException
     */
    public function testDeleteAll()
    {
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->deleteAll([$object]);
    }

    /**
     * @return ReadOnlyRepository
     */
    protected function getRepository()
    {
        $repository = $this->getMockBuilder(ReadOnlyRepository::class)
            ->setConstructorArgs([$this->connection, $this->objectMapper, $this->cache])
            ->setMethods(['fetchAll'])->getMock();

        return $repository;
    }
}
