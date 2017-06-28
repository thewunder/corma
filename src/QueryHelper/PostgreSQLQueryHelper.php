<?php
namespace Corma\QueryHelper;

use Doctrine\DBAL\DBALException;

class PostgreSQLQueryHelper extends QueryHelper
{
    /**
     * @var string
     */
    private $version;

    /**
     * Use ON CONFLICT (id) DO UPDATE to optimize upsert in PostgreSQL > 9.5
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

        $version = $this->getVersion();
        if (version_compare($version, '9.5', '<')) {
            return parent::massUpsert($table, $rows, $lastInsertId);
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
            $columnsToUpdate[] = "$columnName = EXCLUDED.$columnName";
        }

        $query .= ' ON CONFLICT (' . implode(',', $primaryKeys) . ') DO UPDATE SET ' . implode(', ', $columnsToUpdate);

        $params = $this->getParams($normalizedRows);

        $effected = $this->db->executeUpdate($query, $params);
        $lastInsertId = $this->getLastInsertId($table) - ($effected - $updates - 1);

        return $effected;
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
        if (!$previous || $previous->getCode() != 23505) {
            return false;
        }
        return true;
    }

    protected function getVersion(): string
    {
        if ($this->version) {
            return $this->version;
        }
        $versionString = $this->db->query('SELECT version()')->fetchColumn();
        preg_match('/^PostgreSQL ([\d\.]+).*/', $versionString, $matches);
        $version = $matches[1];
        return $this->version = $version;
    }
}
