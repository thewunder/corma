<?php
namespace Corma\Test\Repository;

use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\AggressiveCachingRepository;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\LimitedArrayCache;
use Corma\DBAL\Connection;
use Corma\DBAL\Query\QueryBuilder;
use Corma\DBAL\Schema\Table;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AggressiveCachingRepositoryTest extends TestCase
{
    private ObjectMapper|MockObject $objectMapper;
    private Connection|MockObject $connection;
    private LimitedArrayCache|MockObject $cache;
    private ObjectManager|MockObject $objectManager;

    public function setUp(): void
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();

        $queryHelper->expects($this->any())->method('buildSelectQuery')->willReturn($queryBuilder);
        $queryHelper->expects($this->any())->method('getDbColumns')->willReturn(new Table('extended_data_objects'));

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager = $objectManager = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()->getMock();
        $objectManager->method('getTable')->willReturn('extended_data_objects');
        $objectManager->method('getIdColumn')->willReturn('id');
        $objectManager->method('extract')->willReturn([]);
        $objectManagerFactory = $this->getMockBuilder(ObjectManagerFactory::class)->disableOriginalConstructor()->getMock();
        $objectManagerFactory->expects($this->any())->method('getManager')->willReturn($objectManager);

        $this->objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $this->objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($queryHelper);
        $this->objectMapper->expects($this->any())->method('getIdentityMap')->willReturn(new LimitedArrayCache());

        $this->cache = $this->getMockBuilder(LimitedArrayCache::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testFind(): void
    {
        $repository = $this->getMockBuilder(AggressiveCachingRepository::class)
            ->setConstructorArgs([$this->connection, $this->objectMapper, $this->cache])
            ->onlyMethods(['findAll', 'fetchOne'])->getMock();

        $object = new ExtendedDataObject();
        $object->setId(1)->setMyColumn('My Value');

        $repository->expects($this->once())->method('findAll');
        $repository->expects($this->any())->method('fetchOne')->willReturn($object);
        $object = $repository->find(1);
        $this->assertInstanceOf(ExtendedDataObject::class, $object);
        $this->assertEquals('My Value', $object->getMyColumn());
    }

    public function testFindByIds(): void
    {
        $repository = $this->getMockBuilder(AggressiveCachingRepository::class)
            ->setConstructorArgs([$this->connection, $this->objectMapper, $this->cache])
            ->onlyMethods(['findAll', 'fetchAll'])->getMock();

        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(1)->setMyColumn('My Value');
        $object2 = new ExtendedDataObject();
        $objects[] = $object2->setId(2)->setMyColumn('My Value 2');

        $repository->expects($this->once())->method('findAll');
        $repository->expects($this->any())->method('fetchAll')->willReturn($objects);
        /** @var ExtendedDataObject[] $objects */
        $objects = $repository->findByIds([1]);
        $this->assertCount(2, $objects);
        $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        $this->assertEquals('My Value', $objects[0]->getMyColumn());
    }

    public function testFindAll(): void
    {
        $this->cache->expects($this->exactly(2))->method('has')->will($this->onConsecutiveCalls(false, true));
        $this->cache->expects($this->once())->method('set');
        $this->cache->expects($this->once())->method('get')->willReturn([['id' =>1, 'myColumn'=>'My Value'], ['id' =>2, 'myColumn'=>'My Value 2']]);

        $repository = $this->getRepository();

        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(1)->setMyColumn('My Value');
        $object2 = new ExtendedDataObject();
        $objects[] = $object2->setId(2)->setMyColumn('My Value 2');

        $repository->expects($this->once())->method('fetchAll')->willReturn($objects);
        $objects = $repository->findAll();
        $this->assertCount(2, $objects);
        $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);

        $repository->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls($object, $object2);
        $this->objectManager->expects($this->exactly(2))->method('getId')->willReturnOnConsecutiveCalls('1', '2');

        /** @var ExtendedDataObject[] $objects */
        $objects = $repository->findAll();
        $this->assertCount(2, $objects);
        $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        $this->assertEquals('My Value', $objects[0]->getMyColumn());
    }

    public function testSave(): void
    {
        $object = new ExtendedDataObject();
        $this->cache->expects($this->once())->method('delete')->with('all_extended_data_objects');
        $repo = $this->getRepository();
        $repo->save($object);
    }

    public function testSaveAll(): void
    {
        $this->cache->expects($this->once())->method('delete')->with('all_extended_data_objects');
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->saveAll([$object]);
    }

    public function testDelete(): void
    {
        $this->cache->expects($this->once())->method('delete')->with('all_extended_data_objects');
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    public function testDeleteAll(): void
    {
        $this->cache->expects($this->once())->method('delete')->with('all_extended_data_objects');
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->deleteAll([$object]);
    }

    protected function getRepository(): AggressiveCachingRepository|MockObject
    {
        return $this->getMockBuilder(AggressiveCachingRepository::class)
            ->setConstructorArgs([$this->connection, $this->objectMapper, $this->cache])
            ->onlyMethods(['fetchAll', 'insert', 'create'])->getMock();
    }
}
