<?php
namespace Corma\QueryHelper;

use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class QueryHelper implements QueryHelperInterface
{
    /**
     * @var Connection
     */
    private $db;
    /**
     * @var Cache
     */
    private $cache;

    public function __construct(Connection $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Build a simple select query for table
     *
     * @param string $table
     * @param array|string $columns
     * @param array $where column => value pairs, value may be an array for an IN() clause
     * @param array $orderBy of column => ASC / DESC pairs
     * @return QueryBuilder
     */
    public function buildSelectQuery($table, $columns = 'main.*', array $where = [], array $orderBy = [])
    {
        $qb = $this->db->createQueryBuilder()->select($columns)->from($this->db->quoteIdentifier($table), 'main');

        $this->processWhereQuery($qb, $where);

        foreach($orderBy as $column => $order) {
            $qb->addOrderBy($this->db->quoteIdentifier($column), $order);
        }

        return $qb;
    }

    /**
     * Build an update query for the provided table
     *
     * @param string $table
     * @param array $update column => value pairs to update in SET clause
     * @param array $where column => value pairs, value may be an array for an IN() clause
     * @return QueryBuilder
     */
    public function buildUpdateQuery($table, array $update, array $where)
    {
        $qb = $this->db->createQueryBuilder()->update($this->db->quoteIdentifier($table), 'main');

        foreach($update as $column => $value) {
            $paramName = self::getParameterName($column);
            if($value === null) {
                $qb->set($this->db->quoteIdentifier($column), 'NULL');
            } else  {
                $qb->set($this->db->quoteIdentifier($column), ":$paramName")
                    ->setParameter($paramName, $value);
            }
        }

        $this->processWhereQuery($qb, $where);

        return $qb;
    }

    /**
     * Update multiple rows
     *
     * @param string $table
     * @param array $update column => value pairs to update in SET clause
     * @param array $where column => value pairs, value may be an array for an IN() clause
     * @return int The number of affected rows.
     */
    public function massUpdate($table, array $update, array $where)
    {
        $qb = $this->buildUpdateQuery($table, $update, $where);
        return $qb->execute();
    }

    /**
     * Insert multiple rows
     *
     * @param string $table
     * @param array $rows array of column => value
     * @return int The number of inserted rows
     */
    public function massInsert($table, array $rows)
    {
        if(empty($rows)) {
            return 0;
        }

        $query = $this->getInsertSql($table, $rows);

        $params = array_map(function($row) {
            return array_values($row);
        }, $rows);

        return $this->db->executeUpdate($query, $params, array_fill(0, count($rows), Connection::PARAM_STR_ARRAY));
    }

    /**
     * Insert multiple rows, if a row with a duplicate key is found will update the row
     * This function assumes that 'id' is the primary key, and is used as a fallback for databases that don't support real upserts
     *
     * @param string $table
     * @param array $rows array of column => value
     * @return int The number of inserted rows
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function massUpsert($table, array $rows)
    {
        if(empty($rows)) {
            return 0;
        }

        $rowsToInsert = [];
        $rowsToUpdate = [];
        foreach($rows as $row) {
            if(!empty($row['id'])) {
                $rowsToUpdate[] = $row;
            } else {
                $rowsToInsert[] = $row;
            }
        }

        $this->db->beginTransaction();

        try {
            $insertCount = $this->massInsert($table, $rowsToInsert);

            foreach($rowsToUpdate as $row) {
                $id = $row['id'];
                unset($row['id']);
                $this->db->update($this->db->quoteIdentifier($table), $this->quoteIdentifiers($row), ['id'=>$id]);
            }
        } catch(\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $insertCount;
    }

    /**
     * Delete multiple rows
     *
     * @param string $table
     * @param array $where column => value pairs, value may be an array for an IN() clause
     * @return int
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function massDelete($table, array $where)
    {
        $identifier = $this->quoteIdentifiers($where);
        return $this->db->delete($this->db->quoteIdentifier($table), $identifier);
    }

    /**
     * Counts the number of results that would be returned by the select query provided
     *
     * @param QueryBuilder $qb
     * @return int
     */
    public function getCount(QueryBuilder $qb)
    {
        if($qb->getType() != QueryBuilder::SELECT) {
            throw new \InvalidArgumentException('Query builder must be a select query');
        }

        $select = $qb->getQueryPart('select');
        $count = (int) $qb->select('COUNT(main.id)')
            ->execute()->fetchColumn();
        $qb->select($select);
        return $count;
    }

    /**
     * Sets the where query part on the provided query
     *
     * @param QueryBuilder $qb
     * @param array $where column => value pairs, value may be an array for an IN() clause
     */
    public function processWhereQuery(QueryBuilder $qb, array $where)
    {
        $firstWhere = true;
        $db = $qb->getConnection();
        foreach ($where as $column => $value) {
            $paramName = $this->getParameterName($column);
            if (is_array($value)) {
                $clause = $db->quoteIdentifier($column) . " IN(:$paramName)";
                $qb->setParameter($paramName, $value, Connection::PARAM_STR_ARRAY);
            } else if($value === null && $this->acceptsNull($qb->getQueryPart('from'), $column)) {
                $clause = $db->getDatabasePlatform()->getIsNullExpression($db->quoteIdentifier($column));
            } else {
                $clause = $db->quoteIdentifier($column) . ' = :' . $paramName;
                $qb->setParameter($paramName, $value);
            }

            if ($firstWhere) {
                $qb->where($clause);
                $firstWhere = false;
            } else {
                $qb->andWhere($clause);
            }
        }
    }

    /**
     * @param array $from The from part of the query builder
     * @param string $column
     * @return bool
     */
    protected function acceptsNull(array $from, $column)
    {
        foreach($from as $tableInfo) {
            $table = str_replace($this->db->getDatabasePlatform()->getIdentifierQuoteCharacter(), '', $tableInfo['table']);
            $columns = $this->getDbColumns($table);
            if(!isset($columns[$column])) {
                continue;
            }
            return $columns[$column];
        }
        return false;
    }

    /**
     * Returns table metadata for the provided table
     *
     * @param string $table
     * @return array column => accepts null (bool)
     */
    public function getDbColumns($table)
    {
        $key = 'db_columns.'.$table;
        if($this->cache->contains($key)) {
            return $this->cache->fetch($key);
        } else {
            $query = 'DESCRIBE ' . $this->db->quoteIdentifier($table);
            $statement = $this->db->prepare($query);
            $statement->execute();
            $dbColumnInfo = $statement->fetchAll(\PDO::FETCH_OBJ);
            $dbColumns = [];
            foreach($dbColumnInfo as $column) {
                $dbColumns[$column->Field] = $column->Null == 'YES' ? true : false;
            }
            $this->cache->save($key, $dbColumns);
            return $dbColumns;
        }
    }

    /**
     * Prepare the parameter name
     *
     * @param $columnName
     * @return string
     */
    protected function getParameterName($columnName)
    {
        //named parameters with the table alias are not handled properly, chop off table alias
        $paramName = preg_replace('/^([\w]+\\.)(.*)/', '$2', $columnName);
        return $paramName;
    }

    /**
     * @param $table
     * @param array $rows
     * @return string INSERT SQL Query
     */
    protected function getInsertSql($table, array $rows)
    {
        $tableName = $this->db->quoteIdentifier($table);
        $columns = array_keys($rows[0]);
        array_walk($columns, function (&$column) {
            $column = $this->db->quoteIdentifier($column);
        });
        $columnStr = implode(', ', $columns);
        $query = "INSERT INTO $tableName ($columnStr) VALUES ";

        $values = array_fill(0, count($rows), '(?)');
        $query .= implode(', ', $values);
        return $query;
    }

    /**
     * @param array $where column => value pairs
     * @return array `column` => value pairs
     */
    protected function quoteIdentifiers(array $where)
    {
        $columns = array_map(function ($column) {
            return $this->db->quoteIdentifier($column);
        }, array_keys($where));
        $identifier = array_combine($columns, array_values($where));
        return $identifier;
    }
}