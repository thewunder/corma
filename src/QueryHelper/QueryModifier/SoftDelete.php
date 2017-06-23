<?php

namespace Corma\QueryHelper\QueryModifier;

use Corma\QueryHelper\QueryHelperInterface;
use Corma\QueryHelper\QueryModifier;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Enables soft deletes for any database table containing the 'isDeleted' column
 */
class SoftDelete extends QueryModifier
{
    const DB_COLUMN = 'isDeleted';

    /**
     * @var QueryHelperInterface
     */
    private $queryHelper;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    public function __construct(QueryHelperInterface $queryHelper)
    {
        $this->queryHelper = $queryHelper;
        $this->connection = $queryHelper->getConnection();
    }

    public function selectQuery(QueryBuilder $qb, string $table, $columns, array $where, array $orderBy): QueryBuilder
    {
        $columns = $this->queryHelper->getDbColumns($table);
        if ($columns->hasColumn(self::DB_COLUMN) && !$this->hasSoftDeleteColumn($where)) {
            $qb->andWhere($this->connection->quoteIdentifier(QueryHelperInterface::TABLE_ALIAS) . '.' .
                $this->connection->quoteIdentifier(self::DB_COLUMN) . ' = FALSE');
            return $qb;
        } else {
            return $qb;
        }
    }

    public function deleteQuery(QueryBuilder $qb, string $table, array $where): QueryBuilder
    {
        $columns = $this->queryHelper->getDbColumns($table);
        if ($columns->hasColumn(self::DB_COLUMN) && !$this->hasSoftDeleteColumn($where)) {
            $qb->update($table, 'main');
            $qb->set($this->connection->quoteIdentifier(self::DB_COLUMN), 'TRUE');
            return $qb;
        } else {
            return $qb;
        }
    }

    protected function hasSoftDeleteColumn(array $where): bool
    {
        if (isset($where[self::DB_COLUMN])) {
            return true;
        }

        foreach (array_keys($where) as $column) {
            if(strpos($column, self::DB_COLUMN)) {
                return true;
            }
        }

        return false;
    }
}