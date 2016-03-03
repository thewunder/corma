<?php
namespace Corma\Test\Util;

use Corma\Util\QueryHelper;
use Corma\Util\QueryHelperInterface;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;

class QueryHelperTest extends \PHPUnit_Framework_TestCase
{
    /** @var  QueryHelperInterface */
    private $queryHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $connection;

    public function setUp()
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->connection->expects($this->any())->method('quoteIdentifier')->will($this->returnCallback(function($column){
            return "`$column`";
        }));

        $this->queryHelper = new QueryHelper($this->connection, new ArrayCache());
    }

    public function testBuildSelectQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['column'=>'value', 'inColumn'=>[1,2,3]], ['column'=>'ASC']);

        $this->assertEquals(QueryBuilder::SELECT, $qb->getType());

        $from = $qb->getQueryPart('from');
        $this->assertEquals([['table'=>'`test_table`', 'alias'=>'main']], $from);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('(`column` = :column) AND (`inColumn` IN(:inColumn))', $where);

        $orderBy = $qb->getQueryPart('orderBy');
        $this->assertEquals(['`column` ASC'], $orderBy);
    }

    public function testBuildUpdateQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildUpdateQuery('test_table', ['columnToSet'=>'new_value'], ['column'=>'value', 'inColumn'=>[1,2,3]]);

        $this->assertEquals(QueryBuilder::UPDATE, $qb->getType());

        $from = $qb->getQueryPart('from');
        $this->assertEquals(['table'=>'`test_table`', 'alias'=>'main'], $from);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('(`column` = :column) AND (`inColumn` IN(:inColumn))', $where);

        $set = $qb->getQueryPart('set');
        $this->assertEquals(['`columnToSet` = :columnToSet'], $set);
    }

    public function testMassUpdate()
    {
        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->setMethods(['buildUpdateQuery'])
            ->setConstructorArgs([$this->connection, new ArrayCache()])
            ->getMock();

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $qb->expects($this->once())->method('execute')->willReturn(5);

        $this->queryHelper->expects($this->once())->method('buildUpdateQuery')
            ->willReturn($qb);

        $rows = $this->queryHelper->massUpdate('test_table', ['column'=>'value'], ['whereColumn'=>'x']);
        $this->assertEquals(5, $rows);
    }

    public function testMassInsert()
    {
        $this->connection->expects($this->once())->method('executeUpdate')
            ->with('INSERT INTO `test_table` (`column1`, `column2`) VALUES (?), (?)',
                [[1,2], [3,4]], [Connection::PARAM_STR_ARRAY, Connection::PARAM_STR_ARRAY])
            ->willReturn(4);

        $this->queryHelper->massInsert('test_table', [['column1'=>1, 'column2'=>2], ['column1'=>3, 'column2'=>4]]);
    }

    public function testMassDelete()
    {
        $this->connection->expects($this->once())->method('delete')
            ->with('`test_table`', ['column'=>'value'])->willReturn(29);
        $rows = $this->queryHelper->massDelete('test_table', ['column'=>'value']);
        $this->assertEquals(29, $rows);
    }

    public function testGetCount()
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockStatement = $this->getMockBuilder(Statement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockStatement->expects($this->once())->method('fetchColumn')->willReturn(9);

        $qb->expects($this->exactly(2))->method('select')
            ->withConsecutive(['COUNT(main.id)'], [null])->will($this->returnSelf());
        $qb->expects($this->once())->method('execute')->willReturn($mockStatement);

        $count = $this->queryHelper->getCount($qb);
        $this->assertEquals(9, $count);
    }
}