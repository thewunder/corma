<?php

namespace Corma\Test\Integration\Platform;

use Corma\DBAL\Connection;

/**
 * Sets up a specific database for integration tests
 */
abstract class DatabaseTestPlatform
{
    private static ? DatabaseTestPlatform $instance = null;

    protected ?Connection $connection = null;

    public static final function getInstance(): static
    {
        if (self::$instance) {
            return self::$instance;
        }
        return self::$instance = new static();
    }

    public final function getConnection(): Connection
    {
        if ($this->connection) {
            return $this->connection;
        }
        return $this->connection = $this->connect();
    }

    abstract protected function connect(): Connection;
    abstract public function createDatabase(): void;
    abstract public function dropDatabase(): void;
}
