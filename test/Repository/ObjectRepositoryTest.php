<?php
namespace Corma\Test\Repository;

use Corma\DataObject\DataObjectEventInterface;
use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\Exception\ClassNotFoundException;
use Corma\Exception\InvalidArgumentException;
use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Fixtures\Repository\NoClassObjectRepository;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\LimitedArrayCache;
use Corma\Util\UnitOfWork;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ObjectRepositoryTest extends TestCase
{
    private ObjectMapper $objectMapper;
    private Connection|MockObject $connection;
    private EventDispatcherInterface $dispatcher;
    private QueryHelper|MockObject $queryHelper;
    private ObjectManager|MockObject $objectManager;

    public function setUp(): void
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connection->expects($this->any())->method('quoteIdentifier')->will($this->returnCallback(fn($column) => "`$column`"));
        $this->connection->expects($this->any())->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->dispatcher = new EventDispatcher();

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->queryHelper->expects($this->any())->method('getConnection')->willReturn($this->connection);

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager = $objectManager = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()->getMock();
        $objectManager->expects($this->any())->method('getTable')->willReturn('extended_data_objects');
        $objectManager->expects($this->any())->method('getIdColumn')->willReturn('id');
        $objectManagerFactory = $this->getMockBuilder(ObjectManagerFactory::class)->disableOriginalConstructor()->getMock();
        $objectManagerFactory->expects($this->any())->method('getManager')->willReturn($objectManager);

        $this->objectMapper->expects($this->any())->method('unitOfWork')->willReturn(new UnitOfWork($this->objectMapper));
        $this->objectMapper->expects($this->any())->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $this->objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($this->queryHelper);
        $this->objectMapper->expects($this->any())->method('getIdentityMap')->willReturn(new LimitedArrayCache());
    }

    public function testGetClassName(): void
    {
        $repository = $this->getRepository();
        $this->assertEquals(ExtendedDataObject::class, $repository->getClassName());
    }

    public function testClassNotFound(): void
    {
        $this->expectException(ClassNotFoundException::class);
        $repository = new NoClassObjectRepository($this->connection, $this->objectMapper, new LimitedArrayCache());
        $repository->getClassName();
    }

    public function testFind(): void
    {
        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()
            ->onlyMethods(['executeQuery'])->getMock();

        $object = new ExtendedDataObject();
        $object->setId(5);

        $this->objectManager->expects($this->once())->method('fetchOne')->willReturn($object);

        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);
        $repository = $this->getRepository();
        $return = $repository->find(5);
        $this->assertTrue($object === $return);
        $this->assertTrue($object == $repository->find(5)); //test cache hit
    }

    public function testFindByIds(): void
    {
        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()
            ->onlyMethods(['executeQuery'])->getMock();

        $objects = [];
        $objects[] = $object = new ExtendedDataObject();
        $object->setId(5);
        $objects[] = $object = new ExtendedDataObject();
        $object->setId(6);

        $this->objectManager->expects($this->once())->method('fetchAll')->willReturn($objects);
        $this->objectManager->expects($this->exactly(2))->method('getId')->willReturn('5', '6');

        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);
        $repository = $this->getRepository();
        $return = $repository->findByIds([5,6]);
        $this->assertTrue($objects === $return);
    }

    public function testSave(): void
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('testValue');
        $table = $this->getTable();
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($table);
        $this->queryHelper->expects($this->once())->method('massInsert')->with('extended_data_objects', [['myColumn'=>'testValue']]);
        $this->objectManager->expects($this->once())->method('extract')->willReturn(['myColumn'=>'testValue']);
        $this->objectManager->expects($this->once())->method('setNewId');
        $this->objectManager->expects($this->once())->method('isNew')->willReturn(true);
        $repo = $this->getRepository();
        $repo->save($object);
    }

    public function testSaveExisting(): void
    {
        $object = new ExtendedDataObject();
        $object->setId('123')->setMyColumn('testValue');
        $this->connection->expects($this->never())->method('beginTransaction');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable());
        $this->objectManager->expects($this->atLeastOnce())->method('getId')->willReturn('123');
        $this->objectManager->expects($this->once())->method('extract')->willReturn(['myColumn'=>'testValue']);
        $this->queryHelper->expects($this->once())->method('massUpdate')->with('extended_data_objects', ['myColumn'=>'testValue'], ['id'=>'123']);
        $repo = $this->getRepository();
        $repo->save($object);
    }

    public function testSaveIncorrectClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $object = new OtherDataObject();
        $this->getRepository()->save($object);
    }

    public function testSaveWithRelationships(): void
    {
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable());
        $this->connection->expects($this->once())->method('beginTransaction');
        /** @var MockObject|ExtendedDataObject $dataObjectMock */
        $dataObjectMock = $this->getMockBuilder(ExtendedDataObject::class)->onlyMethods(['getMyColumn'])->getMock();
        $dataObjectMock->expects($this->once())->method('getMyColumn');
        $relationshipSaver = fn() => $dataObjectMock->getMyColumn();
        $repository = $this->getRepository();
        $repository->save(new ExtendedDataObject(), $relationshipSaver);
    }

    public function testSaveAll(): void
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('testValue');
        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('testValue 2');

        $this->objectManager->expects($this->exactly(2))->method('extract')->willReturnOnConsecutiveCalls(['myColumn'=>'testValue'], ['myColumn'=>'testValue 2']);
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable());
        $this->queryHelper->expects($this->once())->method('massUpsert')->with('extended_data_objects', [['myColumn'=>'testValue'], ['myColumn'=>'testValue 2']])->willReturn(count($objects));
        $repo = $this->getRepository();
        $inserts = $repo->saveAll($objects);
        $this->assertEquals(count($objects), $inserts);
    }

    public function testSaveAllIncorrectClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $object = new OtherDataObject();
        $this->getRepository()->saveAll([$object]);
    }

    public function testSaveAllEmptyArray(): void
    {
        $inserts = $this->getRepository()->saveAll([]);
        $this->assertEquals(0, $inserts);
    }

    public function testSaveAllWithRelationships(): void
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('testValue');
        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('testValue 2');

        /** @var MockObject|ExtendedDataObject $dataObjectMock */
        $dataObjectMock = $this->getMockBuilder(ExtendedDataObject::class)->onlyMethods(['getMyColumn'])->getMock();
        $dataObjectMock->expects($this->once())->method('getMyColumn');
        $relationshipSaver = fn() => $dataObjectMock->getMyColumn();

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable());
        $repo = $this->getRepository();
        $repo->saveAll($objects, $relationshipSaver);
    }

    public function testDelete(): void
    {
        $object = new ExtendedDataObject();
        $object->setId('123');

        $this->objectManager->expects($this->once())->method('getId')->with($object)->willReturn($object->getId());
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable(false));
        $this->queryHelper->expects($this->once())->method('massDelete')->with('extended_data_objects', ['id'=>$object->getId()]);
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    public function testSoftDelete(): void
    {
        $object = new ExtendedDataObject();
        $object->setId('123')->setMyColumn('testValue');
        $this->objectManager->expects($this->once())->method('getId')->with($object)->willReturn($object->getId());
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable());
        $this->queryHelper->expects($this->once())->method('massDelete')->with('extended_data_objects', ['id'=>$object->getId()]);
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    public function testDeleteIncorrectClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $object = new OtherDataObject();
        $this->getRepository()->delete($object);
    }

    public function testDeleteAll(): void
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId('123');

        $object = new ExtendedDataObject();
        $objects[] = $object->setId('234');

        $this->objectManager->expects($this->once())->method('getIds')->willReturn(['123', '234']);
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable(false));
        $this->queryHelper->expects($this->once())->method('massDelete')->with('extended_data_objects', ['id'=>['123', '234']]);
        $repo = $this->getRepository();
        $repo->deleteAll($objects);
    }

    public function testDeleteAllSoft(): void
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId('123');

        $object = new ExtendedDataObject();
        $objects[] = $object->setId('234');

        $this->objectManager->expects($this->once())->method('getIds')->willReturn(['123', '234']);
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable());
        $this->queryHelper->expects($this->once())->method('massDelete')->with('extended_data_objects', ['id'=>['123', '234']]);
        $repo = $this->getRepository();
        $repo->deleteAll($objects);
    }

    public function testDeleteAllEmptyArray(): void
    {
        $deletes = $this->getRepository()->deleteAll([]);
        $this->assertEquals(0, $deletes);
    }

    public function testDeleteAllIncorrectClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $object = new OtherDataObject();
        $this->getRepository()->deleteAll([$object]);
    }

    public function testFindEvents(): void
    {
        $firedEvents = [
            'DataObject.loaded' => 0,
            'DataObject.ExtendedDataObject.loaded' => 0
        ];
        $this->dispatcher->addListener('DataObject.loaded', function () use (&$firedEvents) {
            $firedEvents['DataObject.loaded'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.loaded', function () use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.loaded'] ++;
        });

        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
        $mockResult = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();
        $mockQb->expects($this->once())->method('executeQuery')->willReturn($mockResult);
        $mockQb->expects($this->once())->method('setMaxResults')->willReturnSelf();

        $this->objectManager->expects($this->once())->method('fetchOne')->willReturn(new ExtendedDataObject());
        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);

        $repo = $this->getRepository();
        $repo->find('234');

        $this->assertEquals(1, $firedEvents['DataObject.loaded']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.loaded']);
    }

    public function testFindAllEvents(): void
    {
        $firedEvents = [
            'DataObject.loaded' => 0,
            'DataObject.ExtendedDataObject.loaded' => 0
        ];
        $this->dispatcher->addListener('DataObject.loaded', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.loaded'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.loaded', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.loaded'] ++;
        });

        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();

        $mockResult = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();

        $objects = [new ExtendedDataObject(), new ExtendedDataObject()];

        $mockQb->expects($this->once())->method('executeQuery')->willReturn($mockResult);

        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);

        $repo = $this->getRepository();
        $this->objectManager->expects($this->any())->method('fetchAll')->willReturn($objects);
        $repo->findAll();

        $this->assertEquals(2, $firedEvents['DataObject.loaded']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.loaded']);
    }

    public function testSaveEvents(): void
    {
        $firedEvents = [
            'DataObject.beforeSave' => 0,
            'DataObject.ExtendedDataObject.beforeSave' => 0,
            'DataObject.afterSave' => 0,
            'DataObject.ExtendedDataObject.afterSave' => 0,
            'DataObject.beforeInsert' => 0,
            'DataObject.ExtendedDataObject.beforeInsert' => 0,
            'DataObject.afterInsert' => 0,
            'DataObject.ExtendedDataObject.afterInsert' => 0,
            'DataObject.beforeUpdate' => 0,
            'DataObject.ExtendedDataObject.beforeUpdate' => 0,
            'DataObject.afterUpdate' => 0,
            'DataObject.ExtendedDataObject.afterUpdate' => 0
        ];

        $this->dispatcher->addListener('DataObject.beforeSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeUpdate'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterUpdate'] ++;
        });

        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(new Table('test_table'));

        $repo = $this->getRepository();
        $dataObject = new ExtendedDataObject();
        $repo->save($dataObject);
        $dataObject->setId('12345');
        $this->objectManager->expects($this->any())->method('isNew')->willReturn(true);
        $repo->save($dataObject);

        $this->assertEquals(2, $firedEvents['DataObject.beforeSave']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.beforeSave']);
        $this->assertEquals(2, $firedEvents['DataObject.afterSave']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.afterSave']);

        $this->assertEquals(1, $firedEvents['DataObject.beforeInsert']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.beforeInsert']);
        $this->assertEquals(1, $firedEvents['DataObject.afterInsert']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.afterInsert']);

        $this->assertEquals(1, $firedEvents['DataObject.beforeUpdate']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.beforeUpdate']);
        $this->assertEquals(1, $firedEvents['DataObject.afterUpdate']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.afterUpdate']);
    }

    public function testSaveAllEvents(): void
    {
        $firedEvents = [
            'DataObject.beforeSave' => 0,
            'DataObject.ExtendedDataObject.beforeSave' => 0,
            'DataObject.afterSave' => 0,
            'DataObject.ExtendedDataObject.afterSave' => 0,
            'DataObject.beforeInsert' => 0,
            'DataObject.ExtendedDataObject.beforeInsert' => 0,
            'DataObject.afterInsert' => 0,
            'DataObject.ExtendedDataObject.afterInsert' => 0,
            'DataObject.beforeUpdate' => 0,
            'DataObject.ExtendedDataObject.beforeUpdate' => 0,
            'DataObject.afterUpdate' => 0,
            'DataObject.ExtendedDataObject.afterUpdate' => 0
        ];

        $this->dispatcher->addListener('DataObject.beforeSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeUpdate'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterUpdate'] ++;
        });

        $repo = $this->getRepository();
        $dataObject = new ExtendedDataObject();
        $dataObject->setId('12345');
        $this->objectManager->expects($this->any())->method('getId')->willReturnOnConsecutiveCalls(null, '12345', null, '12345');
        $this->objectManager->expects($this->any())->method('extract')->willReturn([]);
        $repo->saveAll([$dataObject, new ExtendedDataObject()]);

        $this->assertEquals(2, $firedEvents['DataObject.beforeSave']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.beforeSave']);
        $this->assertEquals(2, $firedEvents['DataObject.afterSave']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.afterSave']);

        $this->assertEquals(1, $firedEvents['DataObject.beforeInsert']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.beforeInsert']);
        $this->assertEquals(1, $firedEvents['DataObject.afterInsert']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.afterInsert']);

        $this->assertEquals(1, $firedEvents['DataObject.beforeUpdate']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.beforeUpdate']);
        $this->assertEquals(1, $firedEvents['DataObject.afterUpdate']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.afterUpdate']);
    }

    public function testDeleteEvents(): void
    {
        $firedEvents = [
            'DataObject.beforeDelete'                    => 0,
            'DataObject.ExtendedDataObject.beforeDelete' => 0,
            'DataObject.afterDelete'                     => 0,
            'DataObject.ExtendedDataObject.afterDelete'  => 0
        ];

        $this->dispatcher->addListener('DataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeDelete']++;
        });

        $this->dispatcher->addListener('DataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterDelete']++;
        });


        $repo = $this->getRepository();
        $dataObject = new ExtendedDataObject();
        $dataObject->setId('12345');
        $repo->delete($dataObject);

        $this->assertEquals(1, $firedEvents['DataObject.beforeDelete']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.beforeDelete']);
        $this->assertEquals(1, $firedEvents['DataObject.afterDelete']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.afterDelete']);
    }

    public function testDeleteAllEvents(): void
    {
        $firedEvents = [
            'DataObject.beforeDelete'                    => 0,
            'DataObject.ExtendedDataObject.beforeDelete' => 0,
            'DataObject.afterDelete'                     => 0,
            'DataObject.ExtendedDataObject.afterDelete'  => 0
        ];

        $this->dispatcher->addListener('DataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.beforeDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.beforeDelete']++;
        });

        $this->dispatcher->addListener('DataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.afterDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $this->assertInstanceOf(ExtendedDataObject::class, $event->getObject());
            $firedEvents['DataObject.ExtendedDataObject.afterDelete']++;
        });


        $repo = $this->getRepository();
        $dataObjects = [];
        $dataObject = new ExtendedDataObject();
        $dataObjects[] = $dataObject->setId('12345');
        $dataObject = new ExtendedDataObject();
        $dataObjects[] = $dataObject->setId('6789');
        $repo->deleteAll($dataObjects);

        $this->assertEquals(2, $firedEvents['DataObject.beforeDelete']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.beforeDelete']);
        $this->assertEquals(2, $firedEvents['DataObject.afterDelete']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.afterDelete']);
    }

    public function testSaveWith(): void
    {
        $repository = $this->getRepository();
        $reflectionObj = new \ReflectionClass($repository);
        $saveWith = $reflectionObj->getMethod('saveWith');
        $saveWith->setAccessible(true);

        $this->connection->expects($this->atLeastOnce())->method('beginTransaction');
        $this->connection->expects($this->atLeastOnce())->method('commit');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(new Table('test_table'));
        $test = $this;
        $saveWith->invokeArgs($repository, [new ExtendedDataObject(), function (array $objects) use ($test) {
            $test->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        }]);
    }

    public function testSaveWithException(): void
    {
        $this->expectException(\Exception::class);

        $repository = $this->getRepository();
        $reflectionObj = new \ReflectionClass($repository);
        $saveWith = $reflectionObj->getMethod('saveWith');
        $saveWith->setAccessible(true);

        $this->connection->expects($this->once())->method('rollback');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(new Table('test_table'));
        $test = $this;
        $saveWith->invokeArgs($repository, [new ExtendedDataObject(), function (array $objects) use ($test): never {
            throw new \Exception('Testing rollback');
        }]);
    }

    public function testSaveAllWith(): void
    {
        $repository = $this->getRepository();
        $reflectionObj = new \ReflectionClass($repository);
        $saveWith = $reflectionObj->getMethod('saveAllWith');
        $saveWith->setAccessible(true);

        $this->objectManager->expects($this->once())->method('extract')->willReturn([]);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $test = $this;
        $saveWith->invokeArgs($repository, [[new ExtendedDataObject()], function (array $objects) use ($test) {
            $test->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        }]);
    }

    public function testSaveAllWithException(): void
    {
        $this->expectException(\Exception::class);

        $repository = $this->getRepository();
        $reflectionObj = new \ReflectionClass($repository);
        $saveWith = $reflectionObj->getMethod('saveAllWith');
        $saveWith->setAccessible(true);

        $this->objectManager->expects($this->any())->method('extract')->willReturn([]);
        $this->connection->expects($this->once())->method('rollback');
        $saveWith->invokeArgs($repository, [[new ExtendedDataObject()], function (array $objects): never {
            throw new \Exception('Testing rollback');
        }]);
    }

    public function testInvalidPagingStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->getRepository()->findAllInvalidPaged();
    }

    protected function getRepository(): ExtendedDataObjectRepository
    {
        return new ExtendedDataObjectRepository($this->connection, $this->objectMapper, new LimitedArrayCache(), $this->dispatcher);
    }

    private function getTable(bool $softDelete = true): Table
    {
        $table = new Table('extended_data_objects');
        $table->addColumn('id', 'integer');
        if ($softDelete) {
            $table->addColumn('isDeleted', 'boolean');
        }
        $table->addColumn('myColumn', 'string');
        return $table;
    }
}
