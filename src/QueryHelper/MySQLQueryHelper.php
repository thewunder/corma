<?php
namespace Corma\QueryHelper;


class MySQLQueryHelper extends QueryHelper
{
    /**
     * Use ON DUPLICATE KEY UPDATE to optimize upsert in MySQL
     *
     * @param string $table
     * @param array $rows
     * @param null $lastInsertId Optional reference to populate with the last auto increment id
     * @return int Rows affected
     */
    public function massUpsert(string $table, array $rows, &$lastInsertId = null): int
    {
        if (empty($rows)) {
            return 0;
        }

        foreach ($this->modifiers as $modifier) {
            $modifier->upsertQuery($table, $rows);
        }

        $primaryKey = $this->getPrimaryKey($table);
        $updates = $this->countUpdates($rows, $primaryKey);
        $normalizedRows = $this->normalizeRows($table, $rows);
        $query = $this->getInsertSql($table, $normalizedRows);

        $dbColumns = $this->getDbColumns($table);

        $columnsToUpdate = [];
        foreach ($dbColumns->getColumns() as $column) {
            $columnName = $column->getName();
            if ($primaryKey && $column == $primaryKey) {
                continue;
            }

            $columnName = $this->db->quoteIdentifier($columnName);
            $columnsToUpdate[] = "$columnName = VALUES($columnName)";
        }

        $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $columnsToUpdate);

        $params = $this->getParams($normalizedRows);
        
        $effected = $this->db->executeStatement($query, $params);
        $lastInsertId = $this->db->lastInsertId();

        return $effected - $updates; //compensate for mysql returning 2 for each row updated
    }
}
