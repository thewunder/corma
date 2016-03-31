<?php
namespace Corma\QueryHelper;

use Doctrine\DBAL\DBALException;

class MySQLQueryHelper extends QueryHelper
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
            $columnsToUpdate[] = "$column = VALUES($column)";
        }

        $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $columnsToUpdate);
        
        $effected = $this->db->executeUpdate($query, $params, $types);
        $lastInsertId = $this->db->lastInsertId();

        return $effected - $updates; //compensate for mysql returning 2 for each row updated
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
        if(!$previous || $previous->getCode() != 23000) {
            return false;
        }
        return isset($previous->errorInfo[1]) && $previous->errorInfo[1] == 1062;
    }
}
