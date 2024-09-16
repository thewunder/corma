<?php
namespace Corma\Test\Unit\Util;

use Corma\DataObject\ObjectManager;
use Corma\Exception\InvalidArgumentException;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\SeekPagedQuery;
use Corma\DBAL\Connection;
use Corma\DBAL\Platforms\MySQLPlatform;
use Corma\DBAL\Query\Expression\ExpressionBuilder;
use Corma\DBAL\Query\QueryBuilder;
use Corma\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SeekPagedQueryTest extends TestCase
{
    private MockObject|Connection $connection;
    private QueryBuilder|MockObject $qb;
    private QueryHelper|MockObject $queryHelper;
    private MockObject|ObjectManager $objectManager;


    public function setUp(): void
    {
        /** @var MockObject|Connection $connection */
        $this->connection = $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('quoteIdentifier')->willReturnArgument(0);

        $this->qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->qb->method('getConnection')->willReturn($connection);
        $this->qb->method('getGroupBy')->willReturn([]);
        $this->qb->method('getOrderBy')->willReturn([]);
        $this->qb->method('expr')->willReturn(new ExpressionBuilder($connection));

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();


        $this->queryHelper->method('getConnection')->willReturn($connection);

        $this->objectManager = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testConstructor(): void
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $pagedQuery = new SeekPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);

        $this->assertEquals(50, $pagedQuery->getPageSize());
        $this->assertEquals(5, $pagedQuery->getPages());
    }

    public function testGroupByQueryUnsupported(): void
    {
        $qb = new QueryBuilder($this->connection);
        $qb->groupBy('fakeColumn');
        $this->expectException(InvalidArgumentException::class);
        new SeekPagedQuery($qb, $this->queryHelper, $this->objectManager);
    }

    public function testCustomId(): void
    {
        $this->objectManager->method('getIdColumn')->willReturn('custom_id');
        $this->queryHelper->expects($this->once())->method('getCount')->with($this->qb, 'custom_id');

        new SeekPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
    }

    public function testGetResults(): void
    {
        $this->objectManager->method('getIdColumn')->willReturn('id');
        $result = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();

        $this->qb->expects($this->once())->method('setMaxResults')->with(50)->will($this->returnSelf());
        $this->qb->expects($this->once())->method('executeQuery')->willReturn($result);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $this->objectManager->expects($this->once())->method('fetchAll')->with($result);

        $pagedQuery = new SeekPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $pagedQuery->getResults("{\"id\": 3}");
    }

    public function testUsageAsIterator(): void
    {
        $this->objectManager->method('getIdColumn')->willReturn('id');
        $result = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();

        $this->qb->expects($this->any())->method('setMaxResults')->will($this->returnSelf());
        $this->qb->expects($this->any())->method('executeQuery')->willReturn($result);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);
        $this->objectManager->expects($this->exactly(5))->method('extract')
            ->willReturnOnConsecutiveCalls(
                ['id'=>1],
                ['id'=>2],
                ['id'=>3],
                ['id'=>4],
                ['id'=>5]
            );

        $this->objectManager->expects($this->exactly(5))->method('fetchAll')->with($result)
            ->willReturnOnConsecutiveCalls(
                [(new ExtendedDataObject())->setId(1)],
                [(new ExtendedDataObject())->setId(2)],
                [(new ExtendedDataObject())->setId(3)],
                [(new ExtendedDataObject())->setId(4)],
                [(new ExtendedDataObject())->setId(5)]
            );

        $pagedQuery = new SeekPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        /**
         * @var ExtendedDataObject[] $results
         */
        foreach ($pagedQuery as $key => $results) {
            if ($key) {
                $keyData = json_decode((string) $key, true);
                $id = $keyData['id'];
                $this->assertEquals($id, $results[0]->getId());
            } else {
                $this->assertEquals(1, $results[0]->getId()); //null = first page
            }
        }
    }

    public function testGetEmpty(): void
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(0);
        $pagedQuery = new SeekPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $results = $pagedQuery->getResults(null);
        $this->assertEmpty($results);
        $this->assertFalse($pagedQuery->valid());
    }

    public function testGetInvalidPageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SeekPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 0);
    }

    public function testJsonSerialize(): void
    {
        $this->queryHelper->expects($this->any())->method('getCount')->willReturn(205);
        $pagedQuery = new SeekPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $object = $pagedQuery->jsonSerialize();
        $this->assertEquals(50, $object->pageSize);
        $this->assertEquals(5, $object->pages);
        $this->assertEquals(205, $object->resultCount);
        $this->assertTrue(property_exists($object, 'lastResult'));
    }
}
