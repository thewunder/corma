<?php
namespace Corma\QueryHelper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

class PostgreSQLQueryHelper extends QueryHelper
{
    /**
     * Use ON DUPLICATE KEY UPDATE to optimize upsert in MySQL
     *
     * @param string $table
     * @param array $rows
     * @param null $lastInsertId Optional reference to populate with the last auto increment id
     * @return int Rows affected
     * @throws \Doctrine\DBAL\DBALException
     */
    public function massUpsert($table, array $rows, &$lastInsertId = null)
    {
        if(empty($rows)) {
            return 0;
        }

        $version = $this->getVersion();
        if($version < 9.5) {
            return parent::massUpsert($table, $rows, $lastInsertId);
        }

        $dbColumns = $this->getDbColumns($table);

        //Ensure uniform rows
        $normalizedRows = [];
        $updates = 0;
        foreach($rows as $row) {
            $normalizedRow = [];
            if(!empty($row['id'])) {
                $updates++;
            }
            foreach($dbColumns as $column => $acceptNull) {
                $normalizedRow[$column] = isset($row[$column]) ? $row[$column] : null;
            }
            $normalizedRows[] = $normalizedRow;
        }

        $tableName = $this->db->quoteIdentifier($table);
        $columns = array_keys($normalizedRows[0]);
        array_walk($columns, function (&$column) {
            $column = $this->db->quoteIdentifier($column);
        });
        $columnStr = implode(', ', $columns);
        $query = "INSERT INTO $tableName ($columnStr) VALUES ";

        $params = [];
        $types = [];
        $values = [];
        foreach($normalizedRows as $normalizedRow) {
            $rowValues = [];
            foreach($normalizedRow as $value) {
                if($value === null) {
                    $rowValues[] = 'DEFAULT';
                } else {
                    $types[] = \PDO::PARAM_STR;
                    $params[] = $value;
                    $rowValues[] = '?';
                }
            }
            $values[] = '('.implode(', ', $rowValues).')';
        }

        $query .= implode(', ', $values);

        $columnsToUpdate = [];
        foreach($dbColumns as $column => $acceptNull) {
            if($column == 'id') {
                continue;
            }

            $column = $this->db->quoteIdentifier($column);
            $columnsToUpdate[] = "$column = EXCLUDED.$column";
        }

        $query .= ' ON CONFLICT (id) DO UPDATE SET ' . implode(', ', $columnsToUpdate);

        $effected = $this->db->executeUpdate($query, $params, $types);
        $lastInsertId = $this->getLastInsertId($table) - ($effected - $updates - 1);

        return $effected;
    }

    /**
     * Only tested with PDO
     *
     * @param DBALException $error
     * @return bool
     */
    public function isDuplicateException(DBALException $error)
    {
        /** @var \PDOException $previous */
        $previous = $error->getPrevious();
        if(!$previous || $previous->getCode() != 23505) {
            return false;
        }
        return true;
    }

    public function getDbColumns($table)
    {
        $key = 'db_columns.'.$table;
        if($this->cache->contains($key)) {
            return $this->cache->fetch($key);
        } else {
            $query = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = :table';
            $statement = $this->db->prepare($query);
            $statement->execute([':table'=>$table]);
            $dbColumnInfo = $statement->fetchAll(\PDO::FETCH_OBJ);
            $dbColumns = [];
            foreach($dbColumnInfo as $column) {
                $dbColumns[$column->column_name] = $column->is_nullable == 'YES' ? true : false;
            }
            $this->cache->save($key, $dbColumns);
            return $dbColumns;
        }
    }

    /**
     * @return float
     */
    protected function getVersion()
    {
        $versionString = $this->db->query('SELECT version()')->fetchColumn();
        preg_match('/^PostgreSQL ([\d\.]+).*/', $versionString, $matches);
        $version = $matches[1];
        return (float) $version;
    }
}
