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
     * @return int Rows affected (Note that each updated row counts as 2)
     * @throws \Doctrine\DBAL\DBALException
     */
    public function massUpsert($table, array $rows)
    {
        if(empty($rows)) {
            return 0;
        }

        $dbColumns = $this->getDbColumns($table);

        //Ensure uniform rows
        $normalizedRows = [];
        foreach($rows as $row) {
            $normalizedRow = [];
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

        return $this->db->executeUpdate($query, $params, array_fill(0, count($rows), Connection::PARAM_STR_ARRAY));
    }
}