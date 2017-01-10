<?php
namespace Corma\QueryHelper;

use Doctrine\DBAL\DBALException;

class PostgreSQLQueryHelper extends QueryHelper
{
    /**
     * Use ON CONFLICT (id) DO UPDATE to optimize upsert in PostgreSQL > 9.5
     *
     * @param string $table
     * @param array $rows
     * @param null $lastInsertId Optional reference to populate with the last auto increment id
     * @return int Rows affected
     * @throws \Doctrine\DBAL\DBALException
     */
    public function massUpsert(string $table, array $rows, &$lastInsertId = null)
    {
        if (empty($rows)) {
            return 0;
        }

        $version = $this->getVersion();
        if ($version < 9.5) {
            return parent::massUpsert($table, $rows, $lastInsertId);
        }

        $updates = $this->countUpdates($rows);
        $normalizedRows = $this->normalizeRows($table, $rows);
        $query = $this->getInsertSql($table, $normalizedRows);

        $dbColumns = $this->getDbColumns($table);
        $columnsToUpdate = [];
        foreach ($dbColumns as $column => $acceptNull) {
            if ($column == 'id') {
                continue;
            }

            $column = $this->db->quoteIdentifier($column);
            $columnsToUpdate[] = "$column = EXCLUDED.$column";
        }

        $query .= ' ON CONFLICT (id) DO UPDATE SET ' . implode(', ', $columnsToUpdate);

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
