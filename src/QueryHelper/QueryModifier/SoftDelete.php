<?php

namespace Corma\QueryHelper\QueryModifier;

use Corma\QueryHelper\QueryHelperInterface;
use Corma\QueryHelper\QueryModifier;
use Corma\DBAL\Connection;
use Corma\DBAL\Query\QueryBuilder;
use Corma\DBAL\Schema\Table;

/**
 * Enables soft deletes for any database table containing the specified column.
 *
 * This does two things:
 *
 * 1. It transforms delete queries for the table into updates that set the deleted column to true.
 *
 * 2. Any select query that runs through buildSelectQuery will include main.deletedColumn = false in the where clause.
 *    Queries by primary key / id and queries that already include the deleted column will not be modified.
 *
 */
class SoftDelete extends QueryModifier
{
    protected Connection $connection;

    public function __construct(protected QueryHelperInterface $queryHelper, protected string $column = 'isDeleted')
    {
        $this->connection = $queryHelper->getConnection();
    }

    public function selectQuery(QueryBuilder $qb, string $table, array|string $columns, array $where, array $orderBy): QueryBuilder
    {
        $columns = $this->queryHelper->getDbColumns($table);
        if ($columns->hasColumn($this->column) && !$this->hasSoftDeleteColumn($where) && !$this->hasId($columns, $where)) {
            $qb->andWhere($this->connection->quoteIdentifier(QueryHelperInterface::TABLE_ALIAS) . '.' .
                $this->connection->quoteIdentifier($this->column) . ' = FALSE');
        }
        return $qb;
    }

    public function deleteQuery(QueryBuilder $qb, string $table, array $where): QueryBuilder
    {
        $columns = $this->queryHelper->getDbColumns($table);
        if ($columns->hasColumn($this->column) && !$this->hasSoftDeleteColumn($where)) {
            $qb->update($this->connection->quoteIdentifier($table), $this->queryHelper::TABLE_ALIAS);
            $qb->set($this->connection->quoteIdentifier($this->column), 'TRUE');
        }
        return $qb;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function setColumn(string $column): void
    {
        $this->column = $column;
    }

    protected function hasId(Table $table, array $where): bool
    {
        if (!$table->hasPrimaryKey()) {
            return false;
        }

        foreach ($table->getPrimaryKeyColumns() as $column) {
            $columnName = $column->getName();
            if(isset($where[$columnName]) || isset($where[QueryHelperInterface::TABLE_ALIAS. '.' .$columnName])) {
                return true;
            }
        }
        return false;
    }

    protected function hasSoftDeleteColumn(array $where): bool
    {
        if (isset($where[$this->column]) || isset($where[QueryHelperInterface::TABLE_ALIAS. '.' .$this->column])) {
            return true;
        }

        foreach (array_keys($where) as $column) {
            if(str_starts_with($column, $this->column)) {
                return true;
            }
        }

        return false;
    }
}
