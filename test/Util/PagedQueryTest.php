<?php
namespace Corma\Test\Util;

use Corma\DataObject\ObjectManager;
use Corma\Util\PagedQuery;
use Corma\QueryHelper\QueryHelper;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;

class PagedQueryTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $qb;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $queryHelper;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $objectMapper;


    public function setUp()
    {
        $this->qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testConstructor()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectMapper, 50);

        $this->assertEquals(50, $pagedQuery->getPageSize());
        $this->assertEquals(5, $pagedQuery->getPages());
    }

    public function testGetResults()
    {
        $statement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()->getMock();

        $this->qb->expects($this->once())->method('setMaxResults')->with(50)->will($this->returnSelf());
        $this->qb->expects($this->once())->method('setFirstResult')->with(100);
        $this->qb->expects($this->once())->method('execute')->willReturn($statement);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $this->objectMapper->expects($this->once())->method('fetchAll')->with($statement);

        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectMapper, 50);
        $pagedQuery->getResults(3);
        $this->assertEquals(3, $pagedQuery->getPage());
        $this->assertEquals(2, $pagedQuery->getPrev());
        $this->assertEquals(4, $pagedQuery->getNext());
    }

    public function testGetEmpty()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(0);
        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectMapper, 50);
        $results = $pagedQuery->getResults(1);
        $this->assertEmpty($results);
        $this->assertEquals(1, $pagedQuery->getPage());
        $this->assertEquals(0, $pagedQuery->getPrev());
        $this->assertEquals(0, $pagedQuery->getNext());
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testGetInvalidPageSize()
    {
        new PagedQuery($this->qb, $this->queryHelper, $this->objectMapper, 0);
    }

    /**
     * @expectedException \Corma\Exception\InvalidArgumentException
     */
    public function testGetInvalidPage()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);
        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectMapper, 50);
        $pagedQuery->getResults(0);
    }

    public function testJsonSerialize()
    {
        $this->queryHelper->expects($this->any())->method('getCount')->willReturn(205);
        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, $this->objectMapper, 50);
        $object = $pagedQuery->jsonSerialize();
        $this->assertEquals(50, $object->pageSize);
        $this->assertEquals(5, $object->pages);
        $this->assertEquals(205, $object->resultCount);
    }
}
