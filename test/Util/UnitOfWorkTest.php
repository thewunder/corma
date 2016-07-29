<?php
namespace Util;

use Corma\ObjectMapper;
use Corma\QueryHelper\QueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Util\UnitOfWork;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UnitOfWorkTest extends \PHPUnit_Framework_TestCase
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

        $this->dispatcher = new EventDispatcher();

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->queryHelper->expects($this->any())->method('getConnection')->willReturn($this->connection);

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($this->queryHelper);
    }

    public function testExecuteTransaction()
    {
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $unitOfWork->executeTransaction(function () {
        });
    }

    /**
     * @expectedException \Exception
     */
    public function testExecuteTransactionException()
    {
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollback');
        $unitOfWork->executeTransaction(function () {
            throw new \Exception();
        });
    }

    /**
     * @expectedException \Error
     */
    public function testExecuteTransactionError()
    {
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollback');
        $unitOfWork->executeTransaction(function () {
            $x = null;
            $x->gonnaThrow();
        });
    }

    public function testExecuteTransactionExceptionCustomHandler()
    {
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $hasRun = false;
        $unitOfWork->executeTransaction(function () {
            throw new \Exception('My Message');
        }, function (\Exception $e) use (&$hasRun) {
            $hasRun = true;
            $this->assertEquals('My Message', $e->getMessage());
        });
        $this->assertTrue($hasRun);
    }

    public function testFlush()
    {
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $toSave = new ExtendedDataObject();
        $toSave->setId(5);
        $toSaveAll = [new OtherDataObject(), new OtherDataObject()];
        $unitOfWork->save($toSave)
            ->saveAll($toSaveAll);
        $toDelete = new ExtendedDataObject();
        $toDelete->setId(7);
        $toDeleteAll = [new OtherDataObject(), new OtherDataObject()];
        $unitOfWork->delete($toDelete)
            ->deleteAll($toDeleteAll);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->objectMapper->expects($this->once())->method('save')->with($toSave);
        $this->objectMapper->expects($this->once())->method('saveAll')->with($toSaveAll);
        $this->objectMapper->expects($this->once())->method('delete')->with($toDelete);
        $this->objectMapper->expects($this->once())->method('deleteAll')->with($toDeleteAll);
        $unitOfWork->flush();
    }
}
