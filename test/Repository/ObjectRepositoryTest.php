<?php
namespace Corma\Test\Repository;

use Corma\Test\ExtendedDataObject;
use Corma\Util\QueryHelper;
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

    /**
     * @expectedException \Corma\Exception\ClassNotFoundException
     */
    public function testClassNotFound()
    {
        /** @noinspection PhpParamsInspection */
        $repository = new NoClassObjectRepository($this->connection, new EventDispatcher(), $this->queryHelper);
        $repository->getTableName();
    }

    /**
     * @expectedException \Corma\Exception\InvalidClassException
     */
    public function testInvalidClass()
    {
        /** @noinspection PhpParamsInspection */
        $repository = new InvalidClassObjectRepository($this->connection, new EventDispatcher(), $this->queryHelper);
        $repository->getTableName();
    }

    public function testSave()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('testValue');
        $this->queryHelper->expects($this->any())->method('getDbColumns')->willReturn(['id'=>false, 'isDeleted'=>false, 'myColumn'=>false]);
        $this->connection->expects($this->once())->method('insert')->with($object->getTableName(), ['`isDeleted`'=>0,'`myColumn`'=>'testValue']);
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
        $this->connection->expects($this->once())->method('update')->with($object->getTableName(), ['`isDeleted`'=>0,'`myColumn`'=>'testValue'], ['id'=>'123']);
        $repo = $this->getRepository();
        $repo->save($object);
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
     * @return ExtendedDataObjectRepository
     */
    protected function getRepository()
    {
        $repository = new ExtendedDataObjectRepository($this->connection, new EventDispatcher(), $this->queryHelper);
        return $repository;
    }
}
