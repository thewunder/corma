<?php
namespace Corma\Test\Repository;

use Corma\DataObject\DataObjectEventInterface;
use Corma\DataObject\Factory\PdoObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\DataObject\Identifier\AutoIncrementIdentifier;
use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Fixtures\Repository\InvalidClassObjectRepository;
use Corma\Test\Fixtures\Repository\NoClassObjectRepository;
use Corma\Test\Fixtures\Repository\WithDependenciesRepository;
use Corma\Test\Fixtures\WithDependencies;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\Inflector;
use Corma\Util\UnitOfWork;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Statement;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ObjectRepositoryTest extends \PHPUnit_Framework_TestCase
{
    private $objectMapper;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $connection;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $queryHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $objectManager;

    public function setUp()
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connection->expects($this->any())->method('quoteIdentifier')->will($this->returnCallback(function ($column) {
            return "`$column`";
        }));
        $this->connection->expects($this->any())->method('getDatabasePlatform')->willReturn(new MySqlPlatform());

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
    }

    public function testGetClassName()
    {
        $repository = $this->getRepository();
        $this->assertEquals(ExtendedDataObject::class, $repository->getClassName());
    }

    /**
     * @expectedException \Corma\Exception\ClassNotFoundException
     */
    public function testClassNotFound()
    {
        /** @noinspection PhpParamsInspection */
        $repository = new NoClassObjectRepository($this->connection, $this->objectMapper, new ArrayCache());
        $repository->getClassName();
    }

    public function testFind()
    {
        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()
            ->setMethods(['execute'])->getMock();

        $object = new ExtendedDataObject();
        $object->setId(5);

        $this->objectManager->expects($this->once())->method('fetchOne')->willReturn($object);

        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);
        $repository = $this->getRepository();
        $return = $repository->find(5);
        $this->assertTrue($object === $return);
        $this->assertTrue($object == $repository->find(5)); //test cache hit
    }

    public function testSave()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('testValue');
        $table = $this->getTable();
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($table);
        $this->connection->expects($this->once())->method('insert')->with('extended_data_objects', ['`myColumn`'=>'testValue']);
        $this->objectManager->expects($this->once())->method('extract')->willReturn(['myColumn'=>'testValue']);
        $this->objectManager->expects($this->once())->method('setNewId');
        $this->objectManager->expects($this->once())->method('isNew')->willReturn(true);
        $repo = $this->getRepository();
        $repo->save($object);
    }

    public function testSaveExisting()
    {
        $object = new ExtendedDataObject();
        $object->setId('123')->setMyColumn('testValue');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($table = $this->getTable());
        $this->objectManager->expects($this->atLeastOnce())->method('getId')->willReturn(123);
        $this->objectManager->expects($this->once())->method('extract')->willReturn(['myColumn'=>'testValue']);
        $this->connection->expects($this->once())->method('update')->with('extended_data_objects', ['`myColumn`'=>'testValue'], ['id'=>'123']);
        $repo = $this->getRepository();
        $repo->save($object);
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testSaveIncorrectClass()
    {
        $object = new OtherDataObject();
        $this->getRepository()->save($object);
    }

    public function testSaveAll()
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

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testSaveAllIncorrectClass()
    {
        $object = new OtherDataObject();
        $this->getRepository()->saveAll([$object]);
    }

    public function testSaveAllEmptyArray()
    {
        $inserts = $this->getRepository()->saveAll([]);
        $this->assertEquals(0, $inserts);
    }

    public function testDelete()
    {
        $object = new ExtendedDataObject();
        $object->setId('123');

        $this->objectManager->expects($this->once())->method('getId')->with($object)->willReturn($object->getId());
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable(false));
        $this->queryHelper->expects($this->once())->method('massDelete')->with('extended_data_objects', ['id'=>$object->getId()]);
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    public function testSoftDelete()
    {
        $object = new ExtendedDataObject();
        $object->setId('123')->setMyColumn('testValue');
        $this->objectManager->expects($this->once())->method('getId')->with($object)->willReturn($object->getId());
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn($this->getTable());
        $this->queryHelper->expects($this->once())->method('massDelete')->with('extended_data_objects', ['id'=>$object->getId()]);
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testDeleteIncorrectClass()
    {
        $object = new OtherDataObject();
        $this->getRepository()->delete($object);
    }

    public function testDeleteAll()
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

    public function testDeleteAllSoft()
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

    public function testDeleteAllEmptyArray()
    {
        $deletes = $this->getRepository()->deleteAll([]);
        $this->assertEquals(0, $deletes);
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testDeleteAllIncorrectClass()
    {
        $object = new OtherDataObject();
        $this->getRepository()->deleteAll([$object]);
    }

    public function testFindEvents()
    {
        $firedEvents = [
            'DataObject.loaded' => 0,
            'DataObject.ExtendedDataObject.loaded' => 0
        ];
        $this->dispatcher->addListener('DataObject.loaded', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.loaded'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.loaded', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.loaded'] ++;
        });

        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()
            ->setMethods(['execute'])->getMock();

        $mockStatement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()
            ->setMethods(['fetch', 'setFetchMode'])->getMock();

        $mockQb->expects($this->once())->method('execute')->willReturn($mockStatement);

        $this->objectManager->expects($this->once())->method('fetchOne')->willReturn(new ExtendedDataObject());
        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);

        $repo = $this->getRepository();
        $repo->find('234');

        $this->assertEquals(1, $firedEvents['DataObject.loaded']);
        $this->assertEquals(1, $firedEvents['DataObject.ExtendedDataObject.loaded']);
    }

    public function testFindAllEvents()
    {
        $firedEvents = [
            'DataObject.loaded' => 0,
            'DataObject.ExtendedDataObject.loaded' => 0
        ];
        $this->dispatcher->addListener('DataObject.loaded', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.loaded'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.loaded', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.loaded'] ++;
        });

        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()
            ->setMethods(['execute'])->getMock();

        $mockStatement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()
            ->setMethods(['fetchAll'])->getMock();

        $objects = [new ExtendedDataObject(), new ExtendedDataObject()];

        $mockQb->expects($this->once())->method('execute')->willReturn($mockStatement);

        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);

        $repo = $this->getRepository();
        $this->objectManager->expects($this->any())->method('fetchAll')->willReturn($objects);
        $repo->findAll();

        $this->assertEquals(2, $firedEvents['DataObject.loaded']);
        $this->assertEquals(2, $firedEvents['DataObject.ExtendedDataObject.loaded']);
    }

    public function testSaveEvents()
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
            $firedEvents['DataObject.beforeSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeUpdate'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
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

    public function testSaveAllEvents()
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
            $firedEvents['DataObject.beforeSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterSave', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterInsert', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeUpdate'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterUpdate', function (DataObjectEventInterface $event) use (&$firedEvents) {
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

    public function testDeleteEvents()
    {
        $firedEvents = [
            'DataObject.beforeDelete'                    => 0,
            'DataObject.ExtendedDataObject.beforeDelete' => 0,
            'DataObject.afterDelete'                     => 0,
            'DataObject.ExtendedDataObject.afterDelete'  => 0
        ];

        $this->dispatcher->addListener('DataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeDelete']++;
        });

        $this->dispatcher->addListener('DataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
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

    public function testDeleteAllEvents()
    {
        $firedEvents = [
            'DataObject.beforeDelete'                    => 0,
            'DataObject.ExtendedDataObject.beforeDelete' => 0,
            'DataObject.afterDelete'                     => 0,
            'DataObject.ExtendedDataObject.afterDelete'  => 0
        ];

        $this->dispatcher->addListener('DataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeDelete']++;
        });

        $this->dispatcher->addListener('DataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterDelete']++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterDelete', function (DataObjectEventInterface $event) use (&$firedEvents) {
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

    public function testSaveWith()
    {
        $repository = $this->getRepository();
        $reflectionObj = new \ReflectionClass($repository);
        $saveWith = $reflectionObj->getMethod('saveWith');
        $saveWith->setAccessible(true);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(new Table('test_table'));
        $test = $this;
        $saveWith->invokeArgs($repository, [new ExtendedDataObject(), function (array $objects) use ($test) {
            $test->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        }]);
    }

    /**
     * @expectedException \Exception
     */
    public function testSaveWithException()
    {
        $repository = $this->getRepository();
        $reflectionObj = new \ReflectionClass($repository);
        $saveWith = $reflectionObj->getMethod('saveWith');
        $saveWith->setAccessible(true);

        $this->connection->expects($this->once())->method('rollback');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(new Table('test_table'));
        $test = $this;
        $saveWith->invokeArgs($repository, [new ExtendedDataObject(), function (array $objects) use ($test) {
            throw new \Exception('Testing rollback');
        }]);
    }

    public function testSaveAllWith()
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

    /**
     * @expectedException \Exception
     */
    public function testSaveAllWithException()
    {
        $repository = $this->getRepository();
        $reflectionObj = new \ReflectionClass($repository);
        $saveWith = $reflectionObj->getMethod('saveAllWith');
        $saveWith->setAccessible(true);

        $this->objectManager->expects($this->any())->method('extract')->willReturn([]);
        $this->connection->expects($this->once())->method('rollback');
        $test = $this;
        $saveWith->invokeArgs($repository, [[new ExtendedDataObject()], function (array $objects) use ($test) {
            throw new \Exception('Testing rollback');
        }]);
    }

    /**
     * @return ExtendedDataObjectRepository
     */
    protected function getRepository()
    {
        $repository = new ExtendedDataObjectRepository($this->connection, $this->objectMapper, new ArrayCache(), $this->dispatcher);
        return $repository;
    }

    /**
     * @param bool $softDelete
     * @return Table
     */
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
