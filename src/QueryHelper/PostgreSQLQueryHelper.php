<?php
namespace Corma\QueryHelper;

use Corma\Exception\MissingPrimaryKeyException;

class PostgreSQLQueryHelper extends QueryHelper
{
    private ?string $version = null;

    /**
     * Use ON CONFLICT (id) DO UPDATE to optimize upsert in PostgreSQL > 9.5
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

        $version = $this->getVersion();
        if (version_compare($version, '9.5', '<')) {
            return parent::massUpsert($table, $rows, $lastInsertId);
        }

        foreach ($this->modifiers as $modifier) {
            $modifier->upsertQuery($table, $rows);
        }

        $primaryKey = $this->getPrimaryKey($table);
        if (!$primaryKey) {
            throw new MissingPrimaryKeyException("$table must have a primary key to complete this operation");
        }

        $updates = $this->countUpdates($rows, $primaryKey);
        $normalizedRows = $this->normalizeRows($table, $rows);
        $query = $this->getInsertSql($table, $normalizedRows);

        $dbColumns = $this->getDbColumns($table);
        $columnsToUpdate = [];
        foreach ($dbColumns->getColumns() as $column) {
            $columnName = $column->getName();
            if ($column == $primaryKey) {
                continue;
            }

            $columnName = $this->db->quoteIdentifier($columnName);
            $columnsToUpdate[] = "$columnName = EXCLUDED.$columnName";
        }

        $query .= ' ON CONFLICT (' . $primaryKey . ') DO UPDATE SET ' . implode(', ', $columnsToUpdate);

        $params = $this->getParams($normalizedRows);

        $effected = $this->db->executeStatement($query, $params);
        $lastInsertId = $this->getLastInsertId($table, $primaryKey) - ($effected - $updates - 1);

        return $effected;
    }

    protected function getVersion(): string
    {
        if ($this->version) {
            return $this->version;
        }
        $versionString = $this->db->executeQuery('SELECT version()')->fetchOne();
        preg_match('/^PostgreSQL ([\d.]+).*/', (string) $versionString, $matches);
        $version = $matches[1];
        return $this->version = $version;
    }
}
