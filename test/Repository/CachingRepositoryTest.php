<?php
namespace Corma\Test\Repository;

use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\ObjectMapper;
use Corma\QueryHelper\QueryHelper;
use Corma\Test\Fixtures\Caching;
use Corma\Test\Fixtures\Repository\CachingRepository;
use Corma\Util\LimitedArrayCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class CachingRepositoryTest extends TestCase
{

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $objectMapper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $connection;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $queryHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $cache;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $objectManager;

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
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(new Table('cachings'));

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager = $objectManager = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()->getMock();
        $objectManager->method('getTable')->willReturn('cachings');
        $objectManager->method('getIdColumn')->willReturn('id');
        $objectManagerFactory = $this->getMockBuilder(ObjectManagerFactory::class)->disableOriginalConstructor()->getMock();
        $objectManagerFactory->expects($this->any())->method('getManager')->willReturn($objectManager);

        $this->objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $this->objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($this->queryHelper);
        $this->objectMapper->expects($this->any())->method('getIdentityMap')->willReturn(new LimitedArrayCache());

        $this->cache = $this->getMockBuilder(ArrayCache::class)
            ->getMock();
    }

    public function testFindHit()
    {
        $this->cache->expects($this->once())->method('fetch')->with('cachings[9]')->willReturn(['id'=>9]);

        $repository = $this->getRepository();

        $object = new Caching();
        $object->setId(9);
        $repository->expects($this->once())->method('create')->willReturn($object);
        $this->objectManager->expects($this->once())->method('getId')->willReturn(9);
        $object = $repository->find(9);
        $this->assertInstanceOf(Caching::class, $object);
        $this->assertEquals(9, $object->getId());
    }

    public function testFindMiss()
    {
        $this->cache->expects($this->once())->method('fetch')->with('cachings[9]')->willReturn(null);
        $this->cache->expects($this->once())->method('save')->with('cachings[9]', ['id'=>9]);

        $repository = $this->getRepository();
        $willReturn = new Caching();
        $this->objectManager->expects($this->once())->method('getId')->willReturn(9);
        $this->objectManager->expects($this->once())->method('extract')->willReturn(['id'=>9]);
        $repository->expects($this->once())->method('fetchOne')->willReturn($willReturn->setId(9));

        $object = $repository->find(9);
        $this->assertInstanceOf(Caching::class, $object);
        $this->assertEquals(9, $object->getId());
    }

    public function testFindNoCache()
    {
        $this->cache->expects($this->never())->method('fetch');

        $repository = $this->getRepository();
        $willReturn = new Caching();
        $repository->expects($this->once())->method('fetchOne')->willReturn($willReturn->setId(9));

        $object = $repository->find(9, false);
        $this->assertInstanceOf(Caching::class, $object);
        $this->assertEquals(9, $object->getId());
    }

    public function testFindByIds()
    {
        $this->cache->expects($this->once())->method('fetchMultiple')->with(['cachings[9]', 'cachings[10]'])->willReturn(['cachings[9]'=>['id'=>9]]);
        $this->cache->expects($this->once())->method('saveMultiple')->with(['cachings[10]'=>['id'=>10]], 86400);

        $repository = $this->getRepository();
        $willReturn = new Caching();
        $repository->expects($this->once())->method('fetchAll')->willReturn([$willReturn->setId(10)]);
        $repository->expects($this->once())->method('create')->willReturn((new Caching())->setId(9));

        $this->objectManager->expects($this->exactly(4))->method('getId')->willReturnOnConsecutiveCalls(9, 10, 10, 10);
        $this->objectManager->expects($this->once())->method('extract')->willReturnOnConsecutiveCalls(['id'=>10]);
        $objects = $repository->findByIds([9, 10]);
        $this->assertCount(2, $objects);
        $this->assertEquals(9, $objects[0]->getId());
        $this->assertEquals(10, $objects[1]->getId());
    }

    public function testFindByIdsNoCache()
    {
        $this->cache->expects($this->never())->method('fetchMultiple');

        $repository = $this->getRepository();
        $willReturn = new Caching();
        $willReturn2 = new Caching();
        $repository->expects($this->once())->method('fetchAll')->willReturn([$willReturn->setId(9), $willReturn2->setId(10)]);

        $objects = $repository->findByIds([9, 10], false);
        $this->assertCount(2, $objects);
        $this->assertEquals(9, $objects[0]->getId());
        $this->assertEquals(10, $objects[1]->getId());
    }

    public function testSave()
    {
        $this->cache->expects($this->once())->method('save')->with('cachings[9]', ['id'=>9]);

        $repository = $this->getRepository();
        $willReturn = new Caching();

        $this->objectManager->expects($this->any())->method('getId')->willReturn(9);
        $this->objectManager->expects($this->exactly(2))->method('extract')->willReturn(['id'=>9]);
        $return = $repository->save($willReturn->setId(9));
        $this->assertEquals(9, $return->getId());
    }

    public function testDelete()
    {
        $this->cache->expects($this->once())->method('delete')->with('cachings[9]');

        $repository = $this->getRepository();
        $object = new Caching();

        $this->objectManager->method('getId')->willReturn(9);
        $repository->delete($object->setId(9));
    }

    public function testSaveAll()
    {
        $this->cache->expects($this->once())->method('saveMultiple')->with(['cachings[11]'=>['id'=>11], 'cachings[12]'=>['id'=>12]], 86400);

        $repository = $this->getRepository();

        $objects = [];
        $object = new Caching();
        $objects[] = $object->setId(11);
        $object2 = new Caching();
        $objects[] = $object2->setId(12);

        $this->objectManager->method('extract')->willReturnOnConsecutiveCalls(['id'=>11], ['id'=>12], ['id'=>11], ['id'=>12]);
        $this->objectManager->method('getId')->willReturnOnConsecutiveCalls(11, 12, 11, 12, 11, 12);
        $repository->saveAll($objects);
    }

    public function testDeleteAll()
    {
        $this->cache->expects($this->exactly(2))->method('delete')->withConsecutive(['cachings[11]'], ['cachings[12]']);

        $repository = $this->getRepository();

        $objects = [];
        $object = new Caching();
        $objects[] = $object->setId(11);
        $object = new Caching();
        $objects[] = $object->setId(12);

        $this->objectManager->method('getId')->willReturnOnConsecutiveCalls(11, 12);
        $repository->deleteAll($objects);
    }

    /**
     * @return CachingRepository
     */
    protected function getRepository()
    {
        $repository = $this->getMockBuilder(CachingRepository::class)
            ->setConstructorArgs([$this->connection, $this->objectMapper, $this->cache])
            ->setMethods(['fetchAll', 'fetchOne', 'create'])->getMock();

        $repository->setClassName(Caching::class);

        return $repository;
    }
}
