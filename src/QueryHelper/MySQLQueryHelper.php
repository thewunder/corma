<?php
namespace Corma\QueryHelper;

use Doctrine\DBAL\Connection;

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

        $query = $this->getInsertSql($table, $normalizedRows);

        $columnsToUpdate = [];
        foreach($dbColumns as $column => $acceptNull) {
            if($column == 'id') {
                continue;
            }

            $column = $this->db->quoteIdentifier($column);
            $columnsToUpdate[] = "$column = VALUES($column)";
        }

        $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $columnsToUpdate);

        $params = array_map(function($row) {
            return array_values($row);
        }, $normalizedRows);

        $effected = $this->db->executeUpdate($query, $params, array_fill(0, count($rows), Connection::PARAM_STR_ARRAY));
        $lastInsertId = $this->db->lastInsertId();

        return $effected - $updates; //compensate for mysql returning 2 for each row updated
    }
}