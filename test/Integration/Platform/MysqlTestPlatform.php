<?php

namespace Corma\Test\Integration\Platform;

use Corma\DBAL\Connection;
use Corma\DBAL\DriverManager;

final class MysqlTestPlatform extends DatabaseTestPlatform
{
    protected function connect(): Connection
    {
        if (empty(getenv('MYSQL_HOST')) || empty(getenv('MYSQL_USER'))) {
            throw new \RuntimeException('Create a .env file with MYSQL_HOST, MYSQL_USER, and MYSQL_PASS to run this test.');
        }

        $pass = getenv('MYSQL_PASS') ?: '';
        $port = getenv('MYSQL_PORT') ?: 3306;

        return DriverManager::getConnection([
            'driver'=>'pdo_mysql','user'=>getenv('MYSQL_USER'),
            'host'=>getenv('MYSQL_HOST'),
            'port'=>$port,
            'password'=>$pass
        ]);
    }

    public function createDatabase(): void
    {
        $connection = $this->getConnection();
        $connection->executeQuery('DROP DATABASE IF EXISTS corma_test;');
        $connection->executeQuery('CREATE DATABASE corma_test DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;');
        $connection->executeQuery('USE corma_test');
        $connection->executeQuery('CREATE TABLE extended_data_objects (
          id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          isDeleted TINYINT(1) UNSIGNED NOT NULL,
          myColumn VARCHAR(255) NOT NULL,
          myNullableColumn INT(11) UNSIGNED NULL DEFAULT NULL,
          otherDataObjectId INT (11) UNSIGNED NULL,
          polymorphicId INT(11) UNSIGNED NULL DEFAULT NULL,
          polymorphicClass VARCHAR(255) NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1');

        $connection->executeQuery('CREATE TABLE other_data_objects (
          id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          isDeleted TINYINT(1) UNSIGNED NOT NULL,
          `name` VARCHAR(255) NOT NULL,
          `extendedDataObjectId` INT (11) UNSIGNED NULL,
          FOREIGN KEY `extendedDataObjectId` (`extendedDataObjectId`) REFERENCES `extended_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1');

        $connection->executeQuery('CREATE TABLE extended_other_rel (
          extendedDataObjectId INT(11) UNSIGNED NOT NULL,
          otherDataObjectId INT(11) UNSIGNED NOT NULL,
          FOREIGN KEY `extendedId` (`extendedDataObjectId`) REFERENCES `extended_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
          FOREIGN KEY `otherId` (`otherDataObjectId`) REFERENCES `other_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
    }

    public function dropDatabase(): void
    {
        $this->getConnection()->executeQuery('DROP DATABASE corma_test');
    }
}
