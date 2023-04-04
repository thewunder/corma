<?php
namespace Corma\QueryHelper;

use Corma\Exception\InvalidArgumentException;
use Corma\Exception\MissingPrimaryKeyException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Psr\SimpleCache\CacheInterface;

class QueryHelper implements QueryHelperInterface
{
    /**
     * Splits a column like the following
     * 2. table alias
     * 3. column
     * 4. optional comparison operator
     */
    const WHERE_COLUMN_REGEX = '/^(([\w]+\\.)|)([\w]+)( LIKE| NOT LIKE| BETWEEN| NOT BETWEEN|([^\w]*))/';

    protected const COMPARISON_OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'BETWEEN', 'NOT BETWEEN'];

    /**
     * @var QueryModifier[]
     */
    protected array $modifiers = [];

    public function __construct(protected Connection $db, protected CacheInterface $cache)
    {
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
    public function buildSelectQuery(string $table, array|string $columns = 'main.*', array $where = [], array $orderBy = []): QueryBuilder
    {
        $qb = $this->db->createQueryBuilder()->select($columns)->from($this->db->quoteIdentifier($table), self::TABLE_ALIAS);

        $this->processWhereQuery($qb, $where);

        foreach ($orderBy as $column => $order) {
            $qb->addOrderBy($this->db->quoteIdentifier($column), $order);
        }

        foreach ($this->modifiers as $modifier) {
            $qb = $modifier->selectQuery($qb, $table, $columns, $where, $orderBy);
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
    public function buildUpdateQuery(string $table, array $update, array $where): QueryBuilder
    {
        $qb = $this->db->createQueryBuilder()->update($this->db->quoteIdentifier($table), self::TABLE_ALIAS);

        foreach ($update as $column => $value) {
            $paramName = $this->getParameterName($column, $qb);
            if ($value === null) {
                $qb->set($this->db->quoteIdentifier($column), 'NULL');
            } else {
                if(is_bool($value)) {
                    $value = $this->db->getDatabasePlatform()->convertBooleans($value);
                }

                $qb->set($this->db->quoteIdentifier($column), ":$paramName")
                    ->setParameter($paramName, $value);
            }
        }

        $this->processWhereQuery($qb, $where);

        foreach ($this->modifiers as $modifier) {
            $qb = $modifier->updateQuery($qb, $table, $update, $where);
        }

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
    public function buildDeleteQuery(string $table, array $where): QueryBuilder
    {
        $qb = $this->db->createQueryBuilder()->delete($this->db->quoteIdentifier($table));
        $this->processWhereQuery($qb, $where);

        foreach ($this->modifiers as $modifier) {
            $qb = $modifier->deleteQuery($qb, $table, $where);
        }

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
    public function massUpdate(string $table, array $update, array $where): int
    {
        $qb = $this->buildUpdateQuery($table, $update, $where);
        return $qb->executeStatement();
    }

    /**
     * Insert multiple rows
     *
     * @param string $table
     * @param array $rows array of column => value
     * @return int The number of inserted rows
     */
    public function massInsert(string $table, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        foreach ($this->modifiers as $modifier) {
            $modifier->insertQuery($table, $rows);
        }

        $normalizedRows = $this->normalizeRows($table, $rows);
        $query = $this->getInsertSql($table, $normalizedRows);
        $params = $this->getParams($normalizedRows);

        return $this->db->executeStatement($query, $params);
    }

    /**
     * Insert multiple rows, if a row with a duplicate key is found will update the row
     * This method is used as a fallback for databases that don't support real upserts
     *
     * @param string $table
     * @param array $rows array of column => value
     * @param null $lastInsertId Optional reference to populate with the last auto increment id
     * @return int The number of affected rows
     *
     * @throws ConnectionException
     * @throws \Exception
     */
    public function massUpsert(string $table, array $rows, &$lastInsertId = null): int
    {
        if (empty($rows)) {
            return 0;
        }

        foreach ($this->modifiers as $modifier) {
            $modifier->upsertQuery($table, $rows);
        }

        $rowsToInsert = [];
        $rowsToUpdate = [];

        $primaryKey = $this->getPrimaryKey($table);
        if (!$primaryKey) {
            throw new MissingPrimaryKeyException("$table must have a primary key to complete this operation");
        }

        foreach ($rows as $row) {
            if (!empty($row[$primaryKey])) {
                $rowsToUpdate[] = $row;
            } else {
                $rowsToInsert[] = $row;
            }
        }

        $this->db->beginTransaction();

        try {
            $effected = $this->massInsert($table, $rowsToInsert);
            $lastInsertId = $this->getLastInsertId($table, $primaryKey) - (count($rowsToInsert) - 1);

            foreach ($rowsToUpdate as $row) {
                $id = $row[$primaryKey];
                unset($row[$primaryKey]);
                $effected += $this->buildUpdateQuery($table, $row, [$primaryKey=>$id])->executeStatement();
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
    public function massDelete(string $table, array $where): int
    {
        $qb = $this->buildDeleteQuery($table, $where);
        return $qb->executeStatement();
    }

    /**
     * Counts the number of results that would be returned by the select query provided
     *
     * @param QueryBuilder $qb
     * @param string $idColumn
     * @return int
     */
    public function getCount(QueryBuilder $qb, string $idColumn = 'id'): int
    {
        if ($qb->getType() != QueryBuilder::SELECT) {
            throw new \InvalidArgumentException('Query builder must be a select query');
        }

        $select = $qb->getQueryPart('select');

        if ($qb->getQueryPart('groupBy')) {
            $qb->select('1 AS group_by_row');
            $count = $this->db->executeQuery("SELECT COUNT(*) FROM ({$qb->getSQL()}) AS group_by_count",
                $qb->getParameters(), $qb->getParameterTypes())->fetchOne();
        } else {
            $orderBy = $qb->getQueryPart('orderBy');

            $count = (int) $qb->select("COUNT(main.$idColumn)")
                ->resetQueryPart('orderBy')
                ->executeQuery()->fetchOne();

            foreach($orderBy as $orderByPart) {
                [$column, $dir] = explode(' ', $orderByPart);
                $qb->addOrderBy($column, $dir);
            }
        }

        $qb->select($select);

        return $count;
    }

    /**
     * Sets the where query part on the provided query builder.
     *
     * $where Array keys are the column, plus optionally a comparison operator (=, <, >, <=, >=, <>, !=, LIKE, NOT LIKE, BETWEEN, and NOT BETWEEN).
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
        foreach ($where as $wherePart => $value) {
            $clause = $this->processWhereField($qb, $wherePart, $value);
            $qb->andWhere($clause);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param $wherePart
     * @param $value
     * @return string
     */
    protected function processWhereField(QueryBuilder $qb, $wherePart, $value): string
    {
        $paramName = $this->getParameterName($wherePart, $qb);
        //$paramPlaceHolder = ':' . $paramName;
        $column = $this->getColumnName($wherePart);
        $columnName = $this->db->quoteIdentifier($column);
        $operator = $this->getOperator($wherePart);
        if (str_contains($operator, 'BETWEEN')) {
            if (!is_array($value) || !isset($value[0]) || !isset($value[1])) {
                throw new InvalidArgumentException('BETWEEN value must be a 2 item array with numeric keys');
            }
            $gtParam = $paramName . 'GreaterThan';
            $ltParam = $paramName . 'LessThan';
            $clause = "$columnName $operator :$gtParam AND :$ltParam";
            $qb->setParameter($gtParam, $value[0])
                ->setParameter($ltParam, $value[1]);
            return $clause;
        } elseif (is_array($value)) {
            if ($operator == '<>' || $operator == '!=') {
                $clause = "$columnName NOT IN(:$paramName)";
            } else {
                $clause = "$columnName IN(:$paramName)";
            }
            $qb->setParameter($paramName, $value, Connection::PARAM_STR_ARRAY);
            return $clause;
        } elseif ($value === null && $this->acceptsNull($qb->getQueryPart('from'), $column)) {
            if ($operator == '<>' || $operator == '!=') {
                return "$columnName IS NOT NULL";
            } else {
                return "$columnName IS NULL";
            }
        } else {
            $clause = "$columnName $operator :$paramName";
            $qb->setParameter($paramName, $value);
            return $clause;
        }
    }

    /**
     * @param array $from The from part of the query builder
     * @param string $whereClause
     * @return bool
     */
    protected function acceptsNull(array $from, string $whereClause): bool
    {
        foreach ($from as $tableInfo) {
            $table = str_replace($this->db->getDatabasePlatform()->getIdentifierQuoteCharacter(), '', $tableInfo['table']);
            $columns = $this->getDbColumns($table);
            $column = $this->getColumnName($whereClause, false);
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
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        } else {
            $schemaManager = $this->db->createSchemaManager();
            $tableObj = $schemaManager->listTableDetails($table);
            if (empty($tableObj->getColumns())) {
                $database = $this->db->getDatabase();
                throw new InvalidArgumentException("The table $database.$table does not exist");
            }
            $this->cache->set($key, $tableObj);
            return $tableObj;
        }
    }

    /**
     * @param string $table
     * @param string $column
     * @return string|null
     * @throws Exception
     */
    public function getLastInsertId(string $table, string $column): ?string
    {
        $sequence = null;
        $platform = $this->db->getDatabasePlatform();
        if ($platform->usesSequenceEmulatedIdentityColumns()) {
            $sequence = $platform->getIdentitySequenceName($table, $column);
        }
        return $this->db->lastInsertId($sequence);
    }

    /**
     * Returns the primary key of the table
     *
     * @param string $table
     * @return null|string
     */
    protected function getPrimaryKey(string $table): ?string
    {
        $schema = $this->getDbColumns($table);
        try {
            $primaryKeys = $schema->getPrimaryKeyColumns();
            return reset($primaryKeys)->getName();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get the parameter name for a where condition part
     *
     * @param string $whereCondition
     * @param QueryBuilder $qb
     * @return string
     */
    protected function getParameterName(string $whereCondition, QueryBuilder $qb): string
    {
        //chop off table alias and operator
        $base = $this->getColumnName($whereCondition, false);

        //check for collisions
        $parameterName = $base;
        $i = 2;
        while ($qb->getParameter($parameterName)) {
            $parameterName = $base.$i;
            $i++;
        }
        return $parameterName;
    }

    /**
     * Returns column name, and optionally table alias
     *
     * @param string $whereCondition
     * @param bool $includeTableAlias
     * @return string column name
     */
    protected function getColumnName(string $whereCondition, bool $includeTableAlias = true): string
    {
        return preg_replace(self::WHERE_COLUMN_REGEX, $includeTableAlias ? '$2$3' : '$3', $whereCondition);
    }

    /**
     * Extract the operator from a where condition part, defaults to = if no operator present
     *
     * @param string $columnName
     * @return string
     */
    protected function getOperator(string $columnName): string
    {
        $operator = trim(preg_replace(self::WHERE_COLUMN_REGEX, '$4', $columnName));
        if ($operator && in_array($operator, self::COMPARISON_OPERATORS)) {
            return $operator;
        } else {
            return '=';
        }
    }

    /**
     * @param string $table
     * @param array $normalizedRows
     * @return string INSERT SQL Query
     */
    protected function getInsertSql(string $table, array $normalizedRows): string
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
    protected function normalizeRows(string $table, array $rows): array
    {
        $dbColumns = $this->getDbColumns($table);

        //Ensure uniform rows
        $normalizedRows = [];
        foreach ($rows as $row) {
            $normalizedRow = [];

            foreach ($dbColumns->getColumns() as $column) {
                $columnName = $column->getName();
                $normalizedRow[$columnName] = $row[$columnName] ?? null;
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
    protected function getParams(array $normalizedRows): array
    {
        $params = [];
        foreach ($normalizedRows as $normalizedRow) {
            foreach ($normalizedRow as $value) {
                if(is_bool($value)) {
                    $params[] = $this->db->getDatabasePlatform()->convertBooleans($value);
                } else if ($value !== null) {
                    $params[] = $value;
                }
            }
        }
        return $params;
    }

    /**
     * Counts the number of rows that would be an update (as opposed to insert)
     *
     * If primary key is null this simply returns zero, since this is only used to count
     * the number of effected rows, this potential inaccuracy is preferable to throwing an error.
     *
     * @param array $rows
     * @param string|null $primaryKey
     *
     * @return int
     */
    protected function countUpdates(array $rows, ?string $primaryKey): int
    {
        if (!$primaryKey) {
            return 0;
        }

        $updates = 0;
        foreach ($rows as $row) {
            if (!empty($row[$primaryKey])) {
                $updates++;
            }
        }
        return $updates;
    }

    public function addModifier(QueryModifier $queryModifier): bool
    {
        if(!isset($this->modifiers[get_class($queryModifier)])) {
            $this->modifiers[get_class($queryModifier)] = $queryModifier;
            return true;
        }
        return false;
    }

    public function getModifier(string $className): ?QueryModifier
    {
        return $this->modifiers[$className] ?? null;
    }

    public function removeModifier(string $className): bool
    {
        if (isset($this->modifiers[$className])) {
            unset($this->modifiers[$className]);
            return true;
        }
        return false;
    }

    public function isDuplicateException(Exception $error): bool
    {
        return $error instanceof Exception\UniqueConstraintViolationException;
    }
}
