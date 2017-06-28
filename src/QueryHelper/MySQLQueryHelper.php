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
    public function massUpsert(string $table, array $rows, &$lastInsertId = null): int
    {
        if (empty($rows)) {
            return 0;
        }

        $updates = $this->countUpdates($rows);
        $normalizedRows = $this->normalizeRows($table, $rows);
        $query = $this->getInsertSql($table, $normalizedRows);

        $dbColumns = $this->getDbColumns($table);
        $primaryKeys = $dbColumns->getPrimaryKeyColumns();
        $columnsToUpdate = [];
        foreach ($dbColumns->getColumns() as $column) {
            $columnName = $column->getName();
            if (in_array($column, $primaryKeys)) {
                continue;
            }

            $columnName = $this->db->quoteIdentifier($columnName);
            $columnsToUpdate[] = "$columnName = VALUES($columnName)";
        }

        $query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $columnsToUpdate);

        $params = $this->getParams($normalizedRows);
        
        $effected = $this->db->executeUpdate($query, $params);
        $lastInsertId = $this->db->lastInsertId();

        return $effected - $updates; //compensate for mysql returning 2 for each row updated
    }

    /**
     * Only tested with PDO
     *
     * @param DBALException $error
     * @return bool
     */
    public function isDuplicateException(DBALException $error): bool
    {
        /** @var \PDOException $previous */
        $previous = $error->getPrevious();
        if (!$previous || $previous->getCode() != 23000) {
            return false;
        }
        return isset($previous->errorInfo[1]) && $previous->errorInfo[1] == 1062;
    }
}
