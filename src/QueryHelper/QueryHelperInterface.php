<?php
namespace Corma\QueryHelper;

use Corma\DBAL\Connection;
use Corma\DBAL\Exception;
use Corma\DBAL\Query\QueryBuilder;
use Corma\DBAL\Schema\Table;

/**
 * Builds sql queries and performs other related tasks.
 * The query helper implementation normalizes differences in database systems.
 */
interface QueryHelperInterface
{
    public const TABLE_ALIAS = 'main';

    /**
     * Build a simple select query for table
     *
     * @param array|string $columns
     * @param array $where column => value pairs
     * @param array $orderBy of column => ASC / DESC pairs
     * @return QueryBuilder
     * @see processWhereQuery() For details on $where array
     */
    public function buildSelectQuery(string $table, array|string $columns = 'main.*', array $where = [], array $orderBy = []): QueryBuilder;

    /**
     * Build an update query for the provided table
     *
     * @param array $update column => value pairs to update in SET clause
     * @param array $where column => value pairs
     * @return QueryBuilder
     * @see processWhereQuery() For details on $where array
     */
    public function buildUpdateQuery(string $table, array $update, array $where): QueryBuilder;

    /**
     * Build a delete query for the provided table
     *
     * @param array $where column => value pairs
     * @return QueryBuilder
     * @see processWhereQuery() For details on $where array
     */
    public function buildDeleteQuery(string $table, array $where): QueryBuilder;

    /**
     * Update multiple rows
     *
     * @param array $update column => value pairs to update in SET clause
     * @param array $where column => value pairs
     * @return int The number of affected rows.
     * @see processWhereQuery() For details on $where array
     */
    public function massUpdate(string $table, array $update, array $where): int;

    /**
     * Insert multiple rows
     *
     * @param array $rows array of column => value
     * @return int The number of inserted rows
     */
    public function massInsert(string $table, array $rows): int;

    /**
     * Insert multiple rows, if a row with a duplicate key is found will update the row, may assume that id is the primary key
     *
     * @param array $rows array of column => value
     * @param null $lastInsertId Optional reference to populate with the last auto increment id
     * @return int The number of affected rows
     */
    public function massUpsert(string $table, array $rows, &$lastInsertId = null): int;

    /**
     * Delete multiple rows
     *
     * @param array $where column => value pairs,
     * @return int Number of affected rows
     * @see processWhereQuery() For details on $where
     */
    public function massDelete(string $table, array $where): int;

    /**
     * Counts the number of results that would be returned by the select query provided
     *
     * @return int
     */
    public function getCount(QueryBuilder $qb, string $idColumn = 'id'): int;

    /**
     * Sets the where query part on the provided query builder.
     *
     * $where Array keys are the column, plus optionally a comparison operator (=, <, >, <=, >=, <>, !=).
     * If the operator is omitted the operator is assumed to be equals.
     *
     * $where Array values may be a simple value or an array of values for an IN() clause.  Array values will ignore
     * A null value means WHERE column IS NULL.  Specifying a null value for a column which does not permit null will
     * result in an InvalidArgumentException.
     *
     * Example:
     * ['column'=>'A', 'columnB >'=> 10, 'nullColumn' => null, 'inColumn'=>[1,2,3]]
     * Translates to (in MySQL):
     * WHERE column = 'A' AND columnB > 10 AND nullColumn IS NULL AND inColumn IN(1,2,3)
     *
     * @param array $where column => value pairs
     */
    public function processWhereQuery(QueryBuilder $qb, array $where);

    /**
     * Returns table metadata for the provided table
     *
     * @return Table
     */
    public function getDbColumns(string $table): Table;

    /**
     * Is this exception caused by a duplicate record (i.e. unique index constraint violation)
     *
     * @return bool
     */
    public function isDuplicateException(Exception $error): bool;

    /**
     * Retrieve the last inserted row id
     *
     * @return string|int|null
     */
    public function getLastInsertId(string $table, string $column): string|int|null;

    /**
     * @return Connection
     */
    public function getConnection(): Connection;

    /**
     * @param QueryModifier $queryModifier Query modifier to add, query modifiers are run in the order they are added
     * @return bool True if modifier was added
     */
    public function addModifier(QueryModifier $queryModifier): bool;

    /**
     * @return QueryModifier|null
     */
    public function getModifier(string $className): ?QueryModifier;

    /**
     * @param string $className Full class name of the query modifier to remove
     * @return bool True if modifier was removed
     */
    public function removeModifier(string $className): bool;
}
