<?php
namespace Corma\Test\QueryHelper;

use Corma\Exception\InvalidArgumentException;
use Corma\QueryHelper\QueryHelper;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\QueryHelper\QueryModifier\SoftDelete;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QueryHelperTest extends TestCase
{
    /** @var  QueryHelperInterface */
    private $queryHelper;

    /** @var MockObject */
    private $connection;

    public function setUp(): void
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->connection->expects($this->any())->method('quoteIdentifier')->will($this->returnCallback(function ($column) {
            return "`$column`";
        }));

        $this->queryHelper = new QueryHelper($this->connection, new ArrayCache());
    }

    public function testBuildSelectQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['main.column'=>'value'], ['column'=>'ASC']);

        $this->assertEquals(QueryBuilder::SELECT, $qb->getType());

        $from = $qb->getQueryPart('from');
        $this->assertEquals([['table'=>'`test_table`', 'alias'=>'main']], $from);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('`main.column` = :column', $where);
        $this->assertEquals('value', $qb->getParameter(':column'));

        $orderBy = $qb->getQueryPart('orderBy');
        $this->assertEquals(['`column` ASC'], $orderBy);
    }

    public function testNotEqualsQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['notEqualColumn !='=>1, 'notEqualColumn2 <>'=>2]);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('(`notEqualColumn` != :notEqualColumn) AND (`notEqualColumn2` <> :notEqualColumn2)', $where);
        $this->assertEquals(1, $qb->getParameter(':notEqualColumn'));
        $this->assertEquals(2, $qb->getParameter(':notEqualColumn2'));
    }

    public function testLessThanQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['lessThanColumn <'=>1, 'lessThanEqColumn <='=>2]);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('(`lessThanColumn` < :lessThanColumn) AND (`lessThanEqColumn` <= :lessThanEqColumn)', $where);
        $this->assertEquals(1, $qb->getParameter(':lessThanColumn'));
        $this->assertEquals(2, $qb->getParameter(':lessThanEqColumn'));
    }

    public function testGreaterThanQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['greaterThanColumn >'=>1, 'greaterThanEqColumn >='=>2]);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('(`greaterThanColumn` > :greaterThanColumn) AND (`greaterThanEqColumn` >= :greaterThanEqColumn)', $where);
        $this->assertEquals(1, $qb->getParameter(':greaterThanColumn'));
        $this->assertEquals(2, $qb->getParameter(':greaterThanEqColumn'));
    }

    public function testLikeQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['likeColumn LIKE'=>'%whatever%']);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('`likeColumn` LIKE :likeColumn', $where);
        $this->assertEquals('%whatever%', $qb->getParameter(':likeColumn'));
    }

    public function testNotLikeQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['likeColumn NOT LIKE'=>'%whatever%']);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('`likeColumn` NOT LIKE :likeColumn', $where);
        $this->assertEquals('%whatever%', $qb->getParameter(':likeColumn'));
    }

    public function testInQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['inColumn'=>[1,2,3]]);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('`inColumn` IN(:inColumn)', $where);
        $this->assertEquals([1,2,3], $qb->getParameter(':inColumn'));
        $this->assertEquals(Connection::PARAM_STR_ARRAY, $qb->getParameterType(':inColumn'));
    }

    public function testNotInQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['inColumn !='=>[1,2,3]]);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('`inColumn` NOT IN(:inColumn)', $where);
        $this->assertEquals([1,2,3], $qb->getParameter(':inColumn'));
        $this->assertEquals(Connection::PARAM_STR_ARRAY, $qb->getParameterType(':inColumn'));
    }

    public function testBetweenQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['column BETWEEN'=>[5, 10]]);
        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('`column` BETWEEN :columnGreaterThan AND :columnLessThan', $where);
        $this->assertEquals(5, $qb->getParameter(':columnGreaterThan'));
        $this->assertEquals(10, $qb->getParameter(':columnLessThan'));
    }

    public function testNotBetweenQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['column NOT BETWEEN'=>[5, 10]]);
        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('`column` NOT BETWEEN :columnGreaterThan AND :columnLessThan', $where);
        $this->assertEquals(5, $qb->getParameter(':columnGreaterThan'));
        $this->assertEquals(10, $qb->getParameter(':columnLessThan'));
    }

    public function testInvalidBetweenQuery()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BETWEEN value must be a 2 item array with numeric keys');
        $qb = new QueryBuilder($this->connection);
        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->onlyMethods(['acceptsNull'])->setConstructorArgs([$this->connection, new ArrayCache()])
            ->getMock();

        $this->queryHelper->processWhereQuery($qb, ['column BETWEEN'=>['asdf']]);
    }

    public function testProcessWhereInvalidArgument()
    {
        $this->expectException(InvalidArgumentException::class);

        $qb = new QueryBuilder($this->connection);
        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->onlyMethods(['acceptsNull'])->setConstructorArgs([$this->connection, new ArrayCache()])
            ->getMock();

        $this->queryHelper->processWhereQuery($qb, ['main.column'=>null]);
    }

    public function testMultipleWhereSameColumn()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildSelectQuery('test_table', 'main.*', ['column >'=>5, 'column <'=>10]);
        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('(`column` > :column) AND (`column` < :column2)', $where);
        $this->assertEquals(5, $qb->getParameter(':column'));
        $this->assertEquals(10, $qb->getParameter(':column2'));
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
            ->onlyMethods(['buildUpdateQuery'])
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
            ->with(
                'INSERT INTO `test_table` (`column1`, `column2`) VALUES (?, ?), (?, ?)',
                [1, 2, 3, 4]
            )
            ->willReturn(2);

        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->onlyMethods(['getDbColumns'])
            ->setConstructorArgs([$this->connection, new ArrayCache()])
            ->getMock();

        $table = new Table('test_table');
        $table->addColumn('column1', 'string', ['notNull'=>false]);
        $table->addColumn('column2', 'string', ['notNull'=>true]);
        $this->queryHelper->expects($this->any())->method('getDbColumns')->with('test_table')
            ->willReturn($table);

        $effected = $this->queryHelper->massInsert('test_table', [['column1'=>1, 'column2'=>2], ['column1'=>3, 'column2'=>4]]);
        $this->assertEquals(2, $effected);
    }

    public function testBuildDeleteQuery()
    {
        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($this->connection));

        $qb = $this->queryHelper->buildDeleteQuery('test_table', ['column'=>'value', 'inColumn'=>[1,2,3]]);

        $this->assertEquals(QueryBuilder::DELETE, $qb->getType());

        $from = $qb->getQueryPart('from');
        $this->assertEquals(['table'=>'`test_table`', 'alias'=>null], $from);

        $where = (string) $qb->getQueryPart('where');
        $this->assertEquals('(`column` = :column) AND (`inColumn` IN(:inColumn))', $where);
    }

    public function testMassDelete()
    {
        $this->queryHelper = $this->getMockBuilder(QueryHelper::class)
            ->onlyMethods(['buildDeleteQuery'])
            ->setConstructorArgs([$this->connection, new ArrayCache()])
            ->getMock();

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $qb->expects($this->once())->method('execute')->willReturn(29);

        $this->queryHelper->expects($this->once())->method('buildDeleteQuery')
            ->with('test_table', ['whereColumn'=>'x'])
            ->willReturn($qb);

        $rows = $this->queryHelper->massDelete('test_table', ['whereColumn'=>'x']);
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
        $qb->method('getQueryPart')->willReturnOnConsecutiveCalls(null, null,[]);
        $qb->expects($this->once())->method('resetQueryPart')->will($this->returnSelf());

        $count = $this->queryHelper->getCount($qb);
        $this->assertEquals(9, $count);
    }

    public function testGetCountWithGroupBy()
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockStatement = $this->getMockBuilder(Statement::class)
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->exactly(2))->method('select')
            ->withConsecutive(['1 AS group_by_row'], [null])->will($this->returnSelf());
        $qb->method('getQueryPart')->willReturnOnConsecutiveCalls(null, ['groupByColumn']);
        $qb->expects($this->once())->method('getParameters')->willReturn([]);
        $qb->expects($this->once())->method('getParameterTypes')->willReturn([]);

        $mockStatement->expects($this->once())->method('fetchColumn')->willReturn(11);
        $this->connection->expects($this->once())->method('executeQuery')->willReturn($mockStatement);

        $count = $this->queryHelper->getCount($qb);
        $this->assertEquals(11, $count);
    }

    public function testGetDbColumns()
    {
        $mockSchemaManager = $this->getMockBuilder(AbstractSchemaManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $tableName = 'test_table';
        $table = new Table($tableName);
        $table->addColumn('test', 'string');

        $mockSchemaManager->expects($this->once())->method('listTableDetails')
            ->with($tableName)->willReturn($table);

        $this->connection->expects($this->once())->method('getSchemaManager')
            ->willReturn($mockSchemaManager);

        $return = $this->queryHelper->getDbColumns($tableName);
        $this->assertEquals($table, $return);
    }

    public function testAddModifier()
    {
        $queryModifier = new SoftDelete($this->queryHelper);
        $success = $this->queryHelper->addModifier($queryModifier);
        $this->assertTrue($success);
        $this->assertNotNull($this->queryHelper->getModifier(SoftDelete::class));
        $success = $this->queryHelper->addModifier($queryModifier);
        $this->assertFalse($success);
    }

    public function testRemoveModifier()
    {
        $queryModifier = new SoftDelete($this->queryHelper);
        $success = $this->queryHelper->removeModifier(SoftDelete::class);
        $this->assertFalse($success);
        $this->queryHelper->addModifier($queryModifier);
        $success = $this->queryHelper->removeModifier(SoftDelete::class);
        $this->assertTrue($success);
        $this->assertNull($this->queryHelper->getModifier(SoftDelete::class));
    }

    public function testMissingTableException()
    {
        $this->expectException(InvalidArgumentException::class);

        $mockSchemaManager = $this->getMockBuilder(AbstractSchemaManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $tableName = 'test_table';
        $table = new Table($tableName);

        $mockSchemaManager->expects($this->once())->method('listTableDetails')
            ->with($tableName)->willReturn($table);

        $this->connection->expects($this->once())->method('getSchemaManager')
            ->willReturn($mockSchemaManager);

        $this->queryHelper->getDbColumns($tableName);
    }
}
