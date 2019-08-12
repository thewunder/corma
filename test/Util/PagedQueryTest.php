<?php
namespace Corma\Test\Util;

use Corma\DataObject\ObjectManager;
use Corma\Util\PagedQuery;
use Corma\QueryHelper\QueryHelper;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;

class PagedQueryTest extends TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $qb;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $queryHelper;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $objectManager;


    public function setUp()
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

        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);

        $this->assertEquals(50, $pagedQuery->getPageSize());
        $this->assertEquals(5, $pagedQuery->getPages());
    }

    public function testCustomId()
    {
        $this->objectManager->expects($this->once())->method('getIdColumn')->willReturn('custom_id');
        $this->queryHelper->expects($this->once())->method('getCount')->with($this->qb, 'custom_id');

        new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
    }

    public function testGetResults()
    {
        $statement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()->getMock();

        $this->qb->expects($this->once())->method('setMaxResults')->with(50)->will($this->returnSelf());
        $this->qb->expects($this->once())->method('setFirstResult')->with(100);
        $this->qb->expects($this->once())->method('execute')->willReturn($statement);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $this->objectManager->expects($this->once())->method('fetchAll')->with($statement);

        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $pagedQuery->getResults(3);
        $this->assertEquals(3, $pagedQuery->getPage());
        $this->assertEquals(2, $pagedQuery->getPrev());
        $this->assertEquals(4, $pagedQuery->getNext());
    }

    public function testUsageAsIterator()
    {
        $statement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()->getMock();

        $this->qb->expects($this->any())->method('setMaxResults')->will($this->returnSelf());
        $this->qb->expects($this->any())->method('execute')->willReturn($statement);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $this->objectManager->expects($this->exactly(5))->method('fetchAll')->with($statement)->willReturnOnConsecutiveCalls([1], [2], [3], [4], [5]);

        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        foreach ($pagedQuery as $i => $results) {
            $this->assertEquals($i, $results[0]);
        }
    }

    public function testGetEmpty()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(0);
        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $results = $pagedQuery->getResults(1);
        $this->assertEmpty($results);
        $this->assertEquals(1, $pagedQuery->getPage());
        $this->assertEquals(0, $pagedQuery->getPrev());
        $this->assertEquals(0, $pagedQuery->getNext());
        $this->assertFalse($pagedQuery->valid());
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testGetInvalidPageSize()
    {
        new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 0);
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testGetInvalidPage()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);
        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $pagedQuery->getResults(0);
    }

    public function testJsonSerialize()
    {
        $this->queryHelper->expects($this->any())->method('getCount')->willReturn(205);
        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectManager, 50);
        $object = $pagedQuery->jsonSerialize();
        $this->assertEquals(50, $object->pageSize);
        $this->assertEquals(5, $object->pages);
        $this->assertEquals(205, $object->resultCount);
    }
}
