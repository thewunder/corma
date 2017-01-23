<?php
namespace Corma\QueryHelper;

use Corma\Exception\BadMethodCallException;
use Corma\Exception\InvalidArgumentException;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;

class QueryHelper implements QueryHelperInterface
{
    /**
     * Splits a column like the following
     * 2. table alias
     * 3. column
     * 4. optional comparison operator
     */
    const WHERE_COLUMN_REGEX = '/^(([\w]+\\.)|)([\w]+)( LIKE| NOT LIKE| BETWEEN| NOT BETWEEN|([^\w]*))/';

    protected $COMPARISON_OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'BETWEEN', 'NOT BETWEEN'];

    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var CacheProvider
     */
    protected $cache;

    public function __construct(Connection $db, CacheProvider $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Build a simple select query for table
     *
     * @param string $table
     * @param array|string $columns
     * @param array $where column => value pairs
     * @param array $orderBy of column => ASC / DESC pairs
     * @return QueryBuilder
     *
     * @see processWhereQuery() For details on $where
     */
    public function buildSelectQuery(string $table, $columns = 'main.*', array $where = [], array $orderBy = [])
    {
        $qb = $this->db->createQueryBuilder()->select($columns)->from($this->db->quoteIdentifier($table), 'main');

        $this->processWhereQuery($qb, $where);

        foreach ($orderBy as $column => $order) {
            $qb->addOrderBy($this->db->quoteIdentifier($column), $order);
        }

        return $qb;
    }

    /**
     * Build an update query for the provided table
     *
     * @param string $table
     * @param array $update column => value pairs to update in SET clause
     * @param array $where column => value pairs
     * @return QueryBuilder
     *
     * @see processWhereQuery() For details on $where
     */
    public function buildUpdateQuery(string $table, array $update, array $where)
    {
        $qb = $this->db->createQueryBuilder()->update($this->db->quoteIdentifier($table), 'main');

        foreach ($update as $column => $value) {
            $paramName = $this->getParameterName($column);
            if ($value === null) {
                $qb->set($this->db->quoteIdentifier($column), 'NULL');
            } else {
                $qb->set($this->db->quoteIdentifier($column), "$paramName")
                    ->setParameter($paramName, $value);
            }
        }

        $this->processWhereQuery($qb, $where);

        return $qb;
    }

    /**
     * Build a delete query for the provided table
     *
     * @param string $table
     * @param array $where column => value pairs
     * @return QueryBuilder
     *
     * @see processWhereQuery() For details on $where
     */
    public function buildDeleteQuery(string $table, array $where)
    {
        $qb = $this->db->createQueryBuilder()->delete($this->db->quoteIdentifier($table));
        $this->processWhereQuery($qb, $where);
        return $qb;
    }

    /**
     * Update multiple rows
     *
     * @param string $table
     * @param array $update column => value pairs to update in SET clause
     * @param array $where column => value pairs
     * @return int The number of affected rows.
     *
     * @see processWhereQuery() For details on $where
     */
    public function massUpdate(string $table, array $update, array $where)
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
    public function massInsert(string $table, array $rows)
    {
        if (empty($rows)) {
            return 0;
        }

        $normalizedRows = $this->normalizeRows($table, $rows);
        $query = $this->getInsertSql($table, $normalizedRows);
        $params = $this->getParams($normalizedRows);

        return $this->db->executeUpdate($query, $params);
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
    public function massUpsert(string $table, array $rows, &$lastInsertId = null)
    {
        if (empty($rows)) {
            return 0;
        }

        $rowsToInsert = [];
        $rowsToUpdate = [];
        foreach ($rows as $row) {
            if (!empty($row['id'])) {
                $rowsToUpdate[] = $row;
            } else {
                $rowsToInsert[] = $row;
            }
        }

        $this->db->beginTransaction();

        try {
            $effected = $this->massInsert($table, $rowsToInsert);
            $lastInsertId = $this->getLastInsertId($table) - (count($rowsToInsert) - 1);

            foreach ($rowsToUpdate as $row) {
                $id = $row['id'];
                unset($row['id']);
                $effected += $this->db->update($this->db->quoteIdentifier($table), $this->quoteIdentifiers($row), ['id'=>$id]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $effected;
    }

    /**
     * Delete multiple rows
     *
     * @param string $table
     * @param array $where column => value pairs
     * @return int Number of affected rows
     *
     * @see processWhereQuery() For details on $where
     */
    public function massDelete(string $table, array $where)
    {
        $qb = $this->buildDeleteQuery($table, $where);
        return $qb->execute();
    }

    /**
     * Counts the number of results that would be returned by the select query provided
     *
     * @param QueryBuilder $qb
     * @return int
     */
    public function getCount(QueryBuilder $qb)
    {
        if ($qb->getType() != QueryBuilder::SELECT) {
            throw new \InvalidArgumentException('Query builder must be a select query');
        }

        $select = $qb->getQueryPart('select');
        $count = (int) $qb->select('COUNT(main.id)')
            ->execute()->fetchColumn();
        $qb->select($select);
        return $count;
    }

    /**
     * Sets the where query part on the provided query builder.
     *
     * $where Array keys are the column, plus optionally a comparison operator (=, <, >, <=, >=, <>, !=, LIKE).
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
     * @param QueryBuilder $qb
     * @param array $where column => value pairs
     */
    public function processWhereQuery(QueryBuilder $qb, array $where)
    {
        $firstWhere = true;
        foreach ($where as $wherePart => $value) {
            $clause = $this->processWhereField($qb, $wherePart, $value);

            if ($firstWhere) {
                $qb->where($clause);
                $firstWhere = false;
            } else {
                $qb->andWhere($clause);
            }
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param $wherePart
     * @param $value
     * @return string
     */
    protected function processWhereField(QueryBuilder $qb, $wherePart, $value)
    {
        $paramName = $this->getParameterName($wherePart);
        $column = $this->getColumnName($wherePart);
        $columnName = $this->db->quoteIdentifier($column);
        $operator = $this->getOperator($wherePart);
        if(strpos($operator, 'BETWEEN') !== false) {
            if (!is_array($value) || !isset($value[0]) || !isset($value[1])) {
                throw new InvalidArgumentException('BETWEEN value must be a 2 item array with numeric keys');
            }
            $gtParam = $paramName . 'GreaterThan';
            $ltParam = $paramName . 'LessThan';
            $clause = "$columnName $operator $gtParam AND $ltParam";
            $qb->setParameter($gtParam, $value[0])
                ->setParameter($ltParam, $value[1]);
            return $clause;
        } else if (is_array($value)) {
            if ($operator == '<>' || $operator == '!=') {
                $clause = "$columnName NOT IN($paramName)";
                $qb->setParameter($paramName, $value, Connection::PARAM_STR_ARRAY);
            } else {
                $clause = "$columnName IN($paramName)";
                $qb->setParameter($paramName, $value, Connection::PARAM_STR_ARRAY);
            }
            return $clause;
        } else if ($value === null && $this->acceptsNull($qb->getQueryPart('from'), $column)) {
            if ($operator == '<>' || $operator == '!=') {
                $clause = $this->db->getDatabasePlatform()->getIsNotNullExpression($columnName);
                return $clause;
            } else {
                $clause = $this->db->getDatabasePlatform()->getIsNullExpression($columnName);
                return $clause;
            }
        } elseif ($value !== null) {
            $clause = "$columnName $operator $paramName";
            $qb->setParameter($paramName, $value);
            return $clause;
        } else {
            throw new InvalidArgumentException("Value for $column is null, but null is not allowed on this column");
        }
    }

    /**
     * @param array $from The from part of the query builder
     * @param string $column
     * @return bool
     */
    protected function acceptsNull(array $from, string $column)
    {
        foreach ($from as $tableInfo) {
            $table = str_replace($this->db->getDatabasePlatform()->getIdentifierQuoteCharacter(), '', $tableInfo['table']);
            $columns = $this->getDbColumns($table);
            if (!$columns->hasColumn($column)) {
                continue;
            }
            return !$columns->getColumn($column)->getNotnull();
        }
        return false;
    }

    /**
     * Returns table metadata for the provided table
     *
     * @param string $table
     * @return Table
     */
    public function getDbColumns(string $table): Table
    {
        $key = 'db_columns.'.$table;
        if ($this->cache->contains($key)) {
            return $this->cache->fetch($key);
        } else {
            $schemaManager = $this->db->getSchemaManager();
            $tableObj = $schemaManager->listTableDetails($table);
            if(empty($tableObj->getColumns())) {
                $database = $this->db->getDatabase();
                throw new InvalidArgumentException("The table $database.$table does not exist");
            }
            $this->cache->save($key, $tableObj);
            return $tableObj;
        }
    }

    /**
     * @param string $table
     * @param string $column
     * @return string
     */
    public function getLastInsertId(string $table, ?string $column = 'id'): ?string
    {
        $sequence = null;
        $platform = $this->db->getDatabasePlatform();
        if ($platform->usesSequenceEmulatedIdentityColumns()) {
            $sequence = $platform->getIdentitySequenceName($table, $column);
        }
        return $this->db->lastInsertId($sequence);
    }

    /**
     * Is this exception caused by a duplicate record (i.e. unique index constraint violation)
     *
     * This will need to be overridden in db specific query helpers
     *
     * @param DBALException $error
     * @return bool
     */
    public function isDuplicateException(DBALException $error): bool
    {
        throw new BadMethodCallException('This method has not been implemented for the current database type');
    }

    /**
     * Get the parameter name for a where condition part
     *
     * @param string $whereCondition
     * @return string
     */
    protected function getParameterName(string $whereCondition)
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
    protected function getColumnName(string $whereCondition)
    {
        return preg_replace(self::WHERE_COLUMN_REGEX, '$2$3', $whereCondition);
    }

    /**
     * Extract the operator from a where condition part, defaults to = if no operator present
     *
     * @param string $columnName
     * @return string
     */
    protected function getOperator(string $columnName)
    {
        $operator = trim(preg_replace(self::WHERE_COLUMN_REGEX, '$4', $columnName));
        if ($operator && in_array($operator, $this->COMPARISON_OPERATORS)) {
            return $operator;
        } else {
            return '=';
        }
    }

    /**
     * @param $table
     * @param array $normalizedRows
     * @return string INSERT SQL Query
     */
    protected function getInsertSql(string $table, array $normalizedRows)
    {
        $tableName = $this->db->quoteIdentifier($table);
        $columns = array_keys($normalizedRows[0]);
        array_walk($columns, function (&$column) {
            $column = $this->db->quoteIdentifier($column);
        });
        $columnStr = implode(', ', $columns);
        $query = "INSERT INTO $tableName ($columnStr) VALUES ";

        $values = [];
        foreach ($normalizedRows as $normalizedRow) {
            $rowValues = [];
            foreach ($normalizedRow as $value) {
                if ($value === null) {
                    $rowValues[] = 'DEFAULT';
                } else {
                    $rowValues[] = '?';
                }
            }
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }

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

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->db;
    }

    /**
     * Creates an array with database columns, all in the same order
     *
     * @param string $table
     * @param array $rows
     * @return array
     */
    protected function normalizeRows(string $table, array $rows)
    {
        $dbColumns = $this->getDbColumns($table);

        //Ensure uniform rows
        $normalizedRows = [];
        foreach ($rows as $row) {
            $normalizedRow = [];

            foreach ($dbColumns->getColumns() as $column) {
                $columnName = $column->getName();
                $normalizedRow[$columnName] = isset($row[$columnName]) ? $row[$columnName] : null;
            }
            $normalizedRows[] = $normalizedRow;
        }
        return $normalizedRows;
    }

    /**
     * Returns an array of parameters
     *
     * @param array $normalizedRows
     * @return array
     */
    protected function getParams(array $normalizedRows)
    {
        $params = [];
        foreach ($normalizedRows as $normalizedRow) {
            foreach ($normalizedRow as $value) {
                if ($value !== null) {
                    $params[] = $value;
                }
            }
        }
        return $params;
    }

    /**
     * @param array $rows
     * @return int
     */
    protected function countUpdates(array $rows)
    {
        $updates = 0;
        foreach ($rows as $row) {
            if (!empty($row['id'])) {
                $updates++;
            }
        }
        return $updates;
    }
}
