<?php

namespace Corma\QueryHelper;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Query modifiers allow you to modify queries before they are executed.
 *
 * @see QueryHelper::addModifier()
 */
abstract class QueryModifier
{
    /**
     * Modify a select query before executing
     *
     * @param QueryBuilder $qb
     * @param string $table
     * @param array|string $columns
     * @param array $where column => value pairs
     * @param array $orderBy of column => ASC / DESC pairs
     * @return QueryBuilder
     */
    public function selectQuery(QueryBuilder $qb, string $table, $columns, array $where, array $orderBy): QueryBuilder
    {
        return $qb;
    }

    /**
     * Modify an insert query before executing
     *
     * @param string $table
     * @param array $rows column => value pairs for each database row
     */
    public function insertQuery(string $table, array &$rows): void
    {
    }

    /**
     * Modify an upsert query before executing
     *
     * @param string $table
     * @param array $rows column => value pairs for each database row
     */
    public function upsertQuery(string $table, array &$rows): void
    {
    }

    /**
     * Modify a update query before executing
     *
     * @param QueryBuilder $qb
     * @param string $table
     * @param array $update column => value pairs to update in SET clause
     * @param array $where column => value pairs
     * @return QueryBuilder
     */
    public function updateQuery(QueryBuilder $qb, string $table, array $update, array $where): QueryBuilder
    {
        return $qb;
    }

    /**
     * Modify a delete query before executing
     *
     * @param QueryBuilder $qb
     * @param string $table
     * @param array $where column => value pairs
     * @return QueryBuilder
     */
    public function deleteQuery(QueryBuilder $qb, string $table, array $where): QueryBuilder
    {
        return $qb;
    }
}
