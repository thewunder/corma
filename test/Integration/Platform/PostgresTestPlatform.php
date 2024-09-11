<?php

namespace Corma\Test\Integration\Platform;

use Corma\DBAL\Connection;
use Corma\DBAL\DriverManager;
use Corma\DBAL\Exception;

final class PostgresTestPlatform extends DatabaseTestPlatform
{

    public function connect(): Connection
    {
        if (empty(getenv('PGSQL_HOST')) || empty(getenv('PGSQL_USER'))) {
            throw new \RuntimeException('Create a .env file with PGSQL_HOST, PGSQL_PORT, PGSQL_USER, and PGSQL_PASS to run this test.');
        }

        $pass = getenv('PGSQL_PASS') ?: '';

        return DriverManager::getConnection([
            'driver'=>'pdo_pgsql',
            'host'=>getenv('PGSQL_HOST'),
            'port'=>getenv('PGSQL_PORT') ?? 5432,
            'user'=> getenv('PGSQL_USER'),
            'password'=>$pass
        ]);
    }

    public function createDatabase(): void
    {
        $connection = $this->getConnection();
        try {
            $this->dropDatabase();
        } catch (Exception) {
        }

        $connection->executeQuery('create schema cormatest');
        $connection->executeQuery('SET search_path TO cormatest');
        $connection->executeQuery('CREATE TABLE cormatest.extended_data_objects (
          id SERIAL PRIMARY KEY,
          "isDeleted" BOOLEAN NOT NULL DEFAULT FALSE,
          "myColumn" VARCHAR(255) NOT NULL,
          "myNullableColumn" INT NULL DEFAULT NULL,
          "otherDataObjectId" INT NULL,
          "polymorphicClass" VARCHAR(255) NULL,
          "polymorphicId" INT NULL DEFAULT NULL
        )');

        $connection->executeQuery('CREATE TABLE cormatest.other_data_objects (
          id SERIAL PRIMARY KEY,
          "isDeleted" BOOLEAN NOT NULL DEFAULT FALSE,
          "name" VARCHAR(255) NOT NULL,
          "extendedDataObjectId" INT NULL REFERENCES extended_data_objects (id) ON DELETE CASCADE 
        )');

        $connection->executeQuery('CREATE TABLE cormatest.extended_other_rel (
          "extendedDataObjectId" INT NOT NULL REFERENCES extended_data_objects (id) ON DELETE CASCADE,
          "otherDataObjectId" INT NOT NULL REFERENCES other_data_objects (id)
        )');
    }

    public function dropDatabase(): void
    {
        $this->getConnection()->executeQuery('drop schema cormatest cascade');
    }
}
