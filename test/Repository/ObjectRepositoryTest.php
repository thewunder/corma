<?php
namespace Corma\Test\Repository;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Fixtures\Repository\InvalidClassObjectRepository;
use Corma\Test\Fixtures\Repository\NoClassObjectRepository;
use Corma\QueryHelper\QueryHelper;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ObjectRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $connection;

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

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
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
        $repository = new NoClassObjectRepository($this->connection, new EventDispatcher(), $this->queryHelper, new ArrayCache());
        $repository->getTableName();
    }

    /**
     * @expectedException \Corma\Exception\InvalidClassException
     */
    public function testInvalidClass()
    {
        /** @noinspection PhpParamsInspection */
        $repository = new InvalidClassObjectRepository($this->connection, new EventDispatcher(), $this->queryHelper, new ArrayCache());
        $repository->getTableName();
    }

    public function testSave()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('testValue');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'isDeleted'=>false, 'myColumn'=>false]);
        $this->connection->expects($this->once())->method('insert')->with($object->getTableName(), ['`myColumn`'=>'testValue']);
        $this->connection->expects($this->any())->method('lastInsertId')->willReturn('123');
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
        $this->assertTrue($object->getIsDeleted());
    }

    public function testSoftDelete()
    {
        $object = new ExtendedDataObject();
        $object->setId('123')->setMyColumn('testValue');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'isDeleted'=>false, 'myColumn'=>false]);
        $this->connection->expects($this->once())->method('update')->with($object->getTableName(), ['isDeleted'=>1], ['id'=>'123']);
        $repo = $this->getRepository();
        $repo->delete($object);
        $this->assertTrue($object->getIsDeleted());
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
        $this->assertTrue($object->getIsDeleted());
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
        $this->assertTrue($object->getIsDeleted());
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testDeleteAllIncorrectClass()
    {
        $object = new OtherDataObject();
        $this->getRepository()->deleteAll([$object]);
    }

    /**
     * @return ExtendedDataObjectRepository
     */
    protected function getRepository()
    {
        $repository = new ExtendedDataObjectRepository($this->connection, new EventDispatcher(), $this->queryHelper, new ArrayCache());
        return $repository;
    }
}
