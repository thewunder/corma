<?php
namespace Corma\Test\Repository;

use Corma\DataObject\DataObjectEventInterface;
use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Fixtures\Repository\InvalidClassObjectRepository;
use Corma\Test\Fixtures\Repository\NoClassObjectRepository;
use Corma\QueryHelper\QueryHelper;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
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

    public function setUp()
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connection->expects($this->any())->method('quoteIdentifier')->will($this->returnCallback(function($column){
            return "`$column`";
        }));
        $this->connection->expects($this->any())->method('getDatabasePlatform')->willReturn(new MySqlPlatform());

        $this->dispatcher = new EventDispatcher();

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($this->queryHelper);
    }

    public function testGetClassName()
    {
        $repository = $this->getRepository();
        $this->assertEquals(ExtendedDataObject::class, $repository->getClassName());
    }

    public function testGetTableName()
    {
        $repository = $this->getRepository();
        $this->assertEquals(ExtendedDataObject::getTableName(), $repository->getTableName());
    }

    public function testCreate()
    {
        $repository = $this->getRepository();
        $object = $repository->create();
        $this->assertInstanceOf(ExtendedDataObject::class, $object);
    }

    /**
     * @expectedException \Corma\Exception\ClassNotFoundException
     */
    public function testClassNotFound()
    {
        /** @noinspection PhpParamsInspection */
        $repository = new NoClassObjectRepository($this->connection, $this->objectMapper, new ArrayCache());
        $repository->getTableName();
    }

    /**
     * @expectedException \Corma\Exception\InvalidClassException
     */
    public function testInvalidClass()
    {
        /** @noinspection PhpParamsInspection */
        $repository = new InvalidClassObjectRepository($this->connection, $this->objectMapper, new ArrayCache());
        $repository->getTableName();
    }

    public function testSave()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('testValue');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'isDeleted'=>false, 'myColumn'=>false]);
        $this->connection->expects($this->once())->method('insert')->with($object->getTableName(), ['`myColumn`'=>'testValue']);
        $this->queryHelper->expects($this->any())->method('getLastInsertId')->willReturn('123');
        $repo = $this->getRepository();
        $repo->save($object);
        $this->assertEquals('123', $object->getId());
    }

    public function testSaveExisting()
    {
        $object = new ExtendedDataObject();
        $object->setId('123')->setMyColumn('testValue');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'isDeleted'=>false, 'myColumn'=>false]);
        $this->connection->expects($this->once())->method('update')->with($object->getTableName(), ['`myColumn`'=>'testValue'], ['id'=>'123']);
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

        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'isDeleted'=>false, 'myColumn'=>false]);
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

    public function testDelete()
    {
        $object = new ExtendedDataObject();
        $object->setId('123');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'myColumn'=>false]);
        $this->connection->expects($this->once())->method('delete')->with($object->getTableName(), ['id'=>'123']);
        $repo = $this->getRepository();
        $repo->delete($object);
        $this->assertTrue($object->isDeleted());
    }

    public function testSoftDelete()
    {
        $object = new ExtendedDataObject();
        $object->setId('123')->setMyColumn('testValue');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'isDeleted'=>false, 'myColumn'=>false]);
        $this->connection->expects($this->once())->method('update')->with($object->getTableName(), ['`isDeleted`'=>1], ['id'=>'123']);
        $repo = $this->getRepository();
        $repo->delete($object);
        $this->assertTrue($object->isDeleted());
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

        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn([]);
        $this->queryHelper->expects($this->once())->method('massDelete')->with($object->getTableName(), ['id'=>['123', '234']]);
        $repo = $this->getRepository();
        $repo->deleteAll($objects);
        $this->assertTrue($object->isDeleted());
    }

    public function testDeleteAllSoft()
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId('123');

        $object = new ExtendedDataObject();
        $objects[] = $object->setId('234');

        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['isDeleted'=>false]);
        $this->queryHelper->expects($this->once())->method('massUpdate')->with($object->getTableName(), ['isDeleted'=>1], ['id'=>['123', '234']]);
        $repo = $this->getRepository();
        $repo->deleteAll($objects);
        $this->assertTrue($object->isDeleted());
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
        $this->dispatcher->addListener('DataObject.loaded', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.loaded'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.loaded', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.loaded'] ++;
        });

        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()
            ->setMethods(['execute'])->getMock();

        $mockStatement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()
            ->setMethods(['fetch', 'setFetchMode'])->getMock();

        $mockStatement->expects($this->once())->method('fetch')->willReturn(new ExtendedDataObject());

        $mockQb->expects($this->once())->method('execute')->willReturn($mockStatement);

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
        $this->dispatcher->addListener('DataObject.loaded', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.loaded'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.loaded', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.loaded'] ++;
        });

        $mockQb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()
            ->setMethods(['execute'])->getMock();

        $mockStatement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()
            ->setMethods(['fetchAll'])->getMock();

        $mockStatement->expects($this->once())->method('fetchAll')->willReturn([new ExtendedDataObject(), new ExtendedDataObject()]);

        $mockQb->expects($this->once())->method('execute')->willReturn($mockStatement);

        $this->queryHelper->expects($this->once())->method('buildSelectQuery')->willReturn($mockQb);

        $repo = $this->getRepository();
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

        $this->dispatcher->addListener('DataObject.beforeSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeUpdate'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterUpdate'] ++;
        });

        $repo = $this->getRepository();
        $dataObject = new ExtendedDataObject();
        $repo->save($dataObject);
        $dataObject->setId('12345');
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

        $this->dispatcher->addListener('DataObject.beforeSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterSave'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterSave', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterSave'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterInsert'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterInsert', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterInsert'] ++;
        });

        $this->dispatcher->addListener('DataObject.beforeUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.beforeUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.beforeUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.beforeUpdate'] ++;
        });

        $this->dispatcher->addListener('DataObject.afterUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.afterUpdate'] ++;
        });
        $this->dispatcher->addListener('DataObject.ExtendedDataObject.afterUpdate', function(DataObjectEventInterface $event) use (&$firedEvents) {
            $firedEvents['DataObject.ExtendedDataObject.afterUpdate'] ++;
        });

        $repo = $this->getRepository();
        $dataObject = new ExtendedDataObject();
        $dataObject->setId('12345');
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

    /**
     * @return ExtendedDataObjectRepository
     */
    protected function getRepository()
    {
        $repository = new ExtendedDataObjectRepository($this->connection, $this->objectMapper, new ArrayCache(), $this->dispatcher);
        return $repository;
    }
}
