<?php

namespace Corma\QueryHelper\QueryModifier;

use Corma\QueryHelper\QueryHelperInterface;
use Corma\QueryHelper\QueryModifier;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;

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
    /**
     * @var QueryHelperInterface
     */
    protected $queryHelper;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $column;

    public function __construct(QueryHelperInterface $queryHelper, string $column = 'isDeleted')
    {
        $this->queryHelper = $queryHelper;
        $this->connection = $queryHelper->getConnection();
        $this->column = $column;
    }

    public function selectQuery(QueryBuilder $qb, string $table, $columns, array $where, array $orderBy): QueryBuilder
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
            $qb->update($table, $this->queryHelper::TABLE_ALIAS);
            $qb->set($this->connection->quoteIdentifier($this->column), 'TRUE');
        }
        return $qb;
    }

    /**
     * @return string
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * @param string $column
     */
    public function setColumn(string $column)
    {
        $this->column = $column;
    }

    protected function hasId(Table $table, array $where): bool
    {
        if (!$table->hasPrimaryKey()) {
            return false;
        }

        foreach ($table->getPrimaryKeyColumns() as $column) {
            if(isset($where[$column]) || isset($where[QueryHelperInterface::TABLE_ALIAS. '.' .$column])) {
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
            if(strpos($column, $this->column) === 0) {
                return true;
            }
        }

        return false;
    }
}
