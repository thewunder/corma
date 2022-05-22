<?php
namespace Corma\Test\Util;

use Corma\DataObject\ObjectManager;
use Corma\Exception\InvalidArgumentException;
use Corma\Util\OffsetPagedQuery;
use Corma\QueryHelper\QueryHelper;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OffsetPagedQueryTest extends TestCase
{
    /** @var MockObject */
    private $qb;
    /** @var MockObject */
    private $queryHelper;
    /** @var MockObject */
    private $objectManager;


    public function setUp(): void
    {
        $this->qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectManager = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testConstructor()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $pagedQuery = new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);

        $this->assertEquals(50, $pagedQuery->getPageSize());
        $this->assertEquals(5, $pagedQuery->getPages());
    }

    public function testCustomId()
    {
        $this->objectManager->expects($this->once())->method('getIdColumn')->willReturn('custom_id');
        $this->queryHelper->expects($this->once())->method('getCount')->with($this->qb, 'custom_id');

        new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
    }

    public function testGetResults()
    {
        $result = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();

        $this->qb->expects($this->once())->method('setMaxResults')->with(50)->will($this->returnSelf());
        $this->qb->expects($this->once())->method('setFirstResult')->with(100);
        $this->qb->expects($this->once())->method('executeQuery')->willReturn($result);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $this->objectManager->expects($this->once())->method('fetchAll')->with($result);

        $pagedQuery = new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $pagedQuery->getResults(3);
        $this->assertEquals(3, $pagedQuery->getPage());
        $this->assertEquals(2, $pagedQuery->getPrev());
        $this->assertEquals(4, $pagedQuery->getNext());
    }

    public function testUsageAsIterator()
    {
        $result = $this->getMockBuilder(Result::class)->disableOriginalConstructor()->getMock();

        $this->qb->expects($this->any())->method('setMaxResults')->will($this->returnSelf());
        $this->qb->expects($this->any())->method('executeQuery')->willReturn($result);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $this->objectManager->expects($this->exactly(5))->method('fetchAll')->with($result)->willReturnOnConsecutiveCalls([1], [2], [3], [4], [5]);

        $pagedQuery = new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        foreach ($pagedQuery as $i => $results) {
            $this->assertEquals($i, $results[0]);
        }
    }

    public function testGetEmpty()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(0);
        $pagedQuery = new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $results = $pagedQuery->getResults(1);
        $this->assertEmpty($results);
        $this->assertEquals(1, $pagedQuery->getPage());
        $this->assertEquals(0, $pagedQuery->getPrev());
        $this->assertEquals(0, $pagedQuery->getNext());
        $this->assertFalse($pagedQuery->valid());
    }

    public function testGetInvalidPageSize()
    {
        $this->expectException(InvalidArgumentException::class);
        new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 0);
    }

    public function testGetInvalidPage()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);
        $pagedQuery = new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $pagedQuery->getResults(0);
    }

    public function testJsonSerialize()
    {
        $this->queryHelper->expects($this->any())->method('getCount')->willReturn(205);
        $pagedQuery = new OffsetPagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $object = $pagedQuery->jsonSerialize();
        $this->assertEquals(50, $object->pageSize);
        $this->assertEquals(5, $object->pages);
        $this->assertEquals(205, $object->resultCount);
    }
}
