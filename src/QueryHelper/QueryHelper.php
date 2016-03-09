<?php
namespace Corma\QueryHelper;

use Corma\Exception\BadMethodCallException;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Query\QueryBuilder;

class QueryHelper implements QueryHelperInterface
{
    /**
     * Splits a column like the following
     * 2. table alias
     * 3. column
     * 4. optional operator
     */
    const WHERE_COLUMN_REGEX = '/^(([\w]+\\.)|)([\w]+)(([^\w]*))/';

    protected $COMPARISON_OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!='];

    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var Cache
     */
    protected $cache;

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
            $paramName = $this->getParameterName($column);
            if($value === null) {
                $qb->set($this->db->quoteIdentifier($column), 'NULL');
            } else  {
                $qb->set($this->db->quoteIdentifier($column), "$paramName")
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
     * @param null $lastInsertId Optional reference to populate with the last auto increment id
     * @return int The number of affected rows
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function massUpsert($table, array $rows, &$lastInsertId = null)
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
            $effected = $this->massInsert($table, $rowsToInsert);
            $lastInsertId = $this->db->lastInsertId();

            foreach($rowsToUpdate as $row) {
                $id = $row['id'];
                unset($row['id']);
                $effected += $this->db->update($this->db->quoteIdentifier($table), $this->quoteIdentifiers($row), ['id'=>$id]);
            }
            $this->db->commit();
        } catch(\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $effected;
    }

    /**
     * Delete multiple rows
     *
     * @param string $table
     * @param array $where column => value pairs, value may be an array for an IN() clause
     * @return int Number of affected rows
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
        foreach ($where as $wherePart => $value) {
            $paramName = $this->getParameterName($wherePart);
            $column = $this->getColumnName($wherePart);
            $columnName = $this->db->quoteIdentifier($column);
            if (is_array($value)) {
                $clause = "$columnName IN($paramName)";
                $qb->setParameter($paramName, $value, Connection::PARAM_STR_ARRAY);
            } else if($value === null && $this->acceptsNull($qb->getQueryPart('from'), $column)) {
                $operator = $this->getOperator($wherePart);
                if($operator == '<>' || $operator == '!=') {
                    $clause = $this->db->getDatabasePlatform()->getIsNotNullExpression($columnName);
                } else {
                    $clause = $this->db->getDatabasePlatform()->getIsNullExpression($columnName);
                }
            } else {
                $operator = $this->getOperator($wherePart);
                $clause = "$columnName $operator $paramName";
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
     * Is this exception caused by a duplicate record (i.e. unique index constraint violation)
     *
     * This will need to be overridden in db specific query helpers
     *
     * @param DBALException $error
     * @return bool
     */
    public function isDuplicateException(DBALException $error)
    {
        throw new BadMethodCallException('This method has not been implemented for the current database type');
    }

    /**
     * Get the parameter name for a where condition part
     *
     * @param string $whereCondition
     * @return string
     */
    protected function getParameterName($whereCondition)
    {
        // chop off table alias and operator
        return ':' . preg_replace(self::WHERE_COLUMN_REGEX, '$3', $whereCondition);
    }

    /**
     * Return table alias (if specified) and column
     *
     * @param $whereCondition
     * @return mixed
     */
    protected function getColumnName($whereCondition)
    {
        return preg_replace(self::WHERE_COLUMN_REGEX, '$2$3', $whereCondition);
    }

    /**
     * Extract the operator from a where condition part, defaults to = if no operator present
     *
     * @param string $columnName
     * @return string
     */
    protected function getOperator($columnName)
    {
        $operator = trim(preg_replace(self::WHERE_COLUMN_REGEX, '$4', $columnName));
        if($operator && in_array($operator, $this->COMPARISON_OPERATORS)) {
            return $operator;
        } else {
            return '=';
        }
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
