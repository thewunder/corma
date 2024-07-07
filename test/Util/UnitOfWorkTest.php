<?php
namespace Corma\Test\Util;

use Corma\ObjectMapper;
use Corma\QueryHelper\QueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Util\UnitOfWork;
use Corma\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class UnitOfWorkTest extends TestCase
{
    private ObjectMapper|MockObject $objectMapper;
    private Connection|MockObject $connection;

    public function setUp(): void
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dispatcher = new EventDispatcher();

        $queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $queryHelper->expects($this->any())->method('getConnection')->willReturn($this->connection);

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($queryHelper);
    }

    public function testExecuteTransaction(): void
    {
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $return = $unitOfWork->executeTransaction(fn() => 7);
        $this->assertEquals(7, $return);
    }

    public function testExecuteTransactionException(): void
    {
        $this->expectException(\Exception::class);
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollback');
        $unitOfWork->executeTransaction(function (): never {
            throw new \Exception();
        });
    }

    public function testExecuteTransactionError(): void
    {
        $this->expectExceptionMessage('Call to a member function gonnaThrow() on null');
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollback');
        $unitOfWork->executeTransaction(function () {
            $x = null;
            $x->gonnaThrow();
        });
    }

    public function testExecuteTransactionExceptionCustomHandler(): void
    {
        $unitOfWork = new UnitOfWork($this->objectMapper);
        $this->connection->expects($this->once())->method('beginTransaction');
        $hasRun = false;
        $unitOfWork->executeTransaction(function (): never {
            throw new \Exception('My Message');
        }, function (\Exception $e) use (&$hasRun) {
            $hasRun = true;
            $this->assertEquals('My Message', $e->getMessage());
        });
        $this->assertTrue($hasRun);
    }

    public function testFlush(): void
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
