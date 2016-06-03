<?php
namespace Corma\Test\Util;

use Corma\Test\Fixtures\ExtendedDataObject;
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

    public function setUp()
    {
        $this->qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testConstructor()
    {
        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, ExtendedDataObject::class, [], 50);

        $this->assertEquals(50, $pagedQuery->getPageSize());
        $this->assertEquals(5, $pagedQuery->getPages());
    }

    public function testGetResults()
    {
        $statement = $this->getMockBuilder(Statement::class)->disableOriginalConstructor()->getMock();
        $statement->expects($this->once())->method('fetchAll')->with(\PDO::FETCH_CLASS, ExtendedDataObject::class);

        $this->qb->expects($this->once())->method('setMaxResults')->with(50)->will($this->returnSelf());
        $this->qb->expects($this->once())->method('setFirstResult')->with(100);
        $this->qb->expects($this->once())->method('execute')->willReturn($statement);

        $this->queryHelper->expects($this->once())->method('getCount')->willReturn(205);

        $pagedQuery = new PagedQuery($this->qb, $this->queryHelper, ExtendedDataObject::class, [], 50);
        $pagedQuery->getResults(3);
        $this->assertEquals(3, $pagedQuery->getPage());
        $this->assertEquals(2, $pagedQuery->getPrev());
        $this->assertEquals(4, $pagedQuery->getNext());
    }
}
