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
                if($acceptNull) {
                    $normalizedRow[$column] = isset($row[$column]) ? $row[$column] : null;
                } else {
                    $normalizedRow[$column] = isset($row[$column]) ? $row[$column] : 'DEFAULT';
                }
            }
            $normalizedRows[] = $normalizedRow;
        }

        $query = $this->getInsertSql($table, $normalizedRows);

        $columnsToUpdate = [];
        foreach($dbColumns as $column => $acceptNull) {
            if($column == 'id') {
                continue;
            }

            $column = $this->db->quoteIdentifier($column);
            $columnsToUpdate[] = "$column = EXCLUDED.$column";
        }

        $query .= ' ON CONFLICT (id) DO UPDATE SET ' . implode(', ', $columnsToUpdate);

        $params = array_map(function($row) {
            return array_values($row);
        }, $normalizedRows);

        $effected = $this->db->executeUpdate($query, $params, array_fill(0, count($rows), Connection::PARAM_STR_ARRAY));
        $lastInsertId = $this->db->lastInsertId();

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
}
