<?php
namespace Corma\Test\Repository;

use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ReadOnlyRepository;
use Corma\QueryHelper\QueryHelper;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ReadOnlyRepositoryTest extends \PHPUnit_Framework_TestCase
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

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();

        $this->queryHelper->expects($this->any())->method('buildSelectQuery')->willReturn($queryBuilder);

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($this->queryHelper);

        $this->cache = $this->getMockBuilder(ArrayCache::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testFindAll()
    {
        $this->cache->expects($this->exactly(2))->method('contains')->will($this->onConsecutiveCalls(false, true));
        $this->cache->expects($this->once())->method('save');
        $this->cache->expects($this->once())->method('fetch')->willReturn([['id' =>1, 'myColumn'=>'My Value'], ['id' =>2, 'myColumn'=>'My Value 2']]);

        $repository = $this->getRepository();

        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(1)->setMyColumn('My Value');
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(2)->setMyColumn('My Value 2');

        $repository->expects($this->once())->method('fetchAll')->willReturn($objects);

        $objects = $repository->findAll();
        $this->assertCount(2, $objects);
        $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);

        /** @var ExtendedDataObject[] $objects */
        $objects = $repository->findAll();
        $this->assertCount(2, $objects);
        $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        $this->assertEquals('My Value', $objects[0]->getMyColumn());
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

