<?php
namespace Corma\Test\Integration;

use Corma\DataObject\ObjectManagerFactory;
use Corma\ObjectMapper;
use Corma\QueryHelper\MySQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\Inflector;
use Corma\Util\LimitedArrayCache;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;

class MysqlIntegrationTest extends BaseIntegrationTest
{
    public function testIsDuplicateException(): void
    {
        $cache = new LimitedArrayCache();
        $mySQLQueryHelper = new MySQLQueryHelper(self::$connection, $cache);
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $objectManagerFactory = ObjectManagerFactory::withDefaults($mySQLQueryHelper, Inflector::build(), $container);
        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $objectMapper, $cache, $this->dispatcher);

        $this->assertFalse($mySQLQueryHelper->isDuplicateException(new Exception()));

        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (Exception $e) {
            $this->assertTrue($mySQLQueryHelper->isDuplicateException($e));
            return;
        }

        $this->markTestIncomplete('Expected Exception was not thrown');
    }

    public function testUpsertWithoutPrimaryKey(): void
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Upsert EDO');
        $this->objectMapper->save($object);

        $otherObject = new OtherDataObject();
        $otherObject->setName('Upsert ODO');
        $this->objectMapper->save($otherObject);

        $return = $this->objectMapper->getQueryHelper()
            ->massUpsert('extended_other_rel', [['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=>$otherObject->getId()]]);

        $this->assertEquals(1, $return);
    }

    protected static function createDatabase()
    {
        if (empty(getenv('MYSQL_HOST')) && file_exists(__DIR__.'/../../.env')) {
            $dotenv = new Dotenv(__DIR__.'/../../');
            $dotenv->load();
        }

        if (empty(getenv('MYSQL_HOST')) || empty(getenv('MYSQL_USER'))) {
            throw new \RuntimeException('Create a .env file with MYSQL_HOST, MYSQL_USER, and MYSQL_PASS to run this test.');
        }

        $pass = getenv('MYSQL_PASS') ? getenv('MYSQL_PASS') : '';

        self::$connection = DriverManager::getConnection(['driver'=>'pdo_mysql','user'=>getenv('MYSQL_USER'), 'host'=>getenv('MYSQL_HOST'), 'password'=>$pass]);
        self::$connection->executeQuery('DROP DATABASE IF EXISTS corma_test;');
        self::$connection->executeQuery('CREATE DATABASE corma_test DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;');
        self::$connection->executeQuery('USE corma_test');
        self::$connection->executeQuery('CREATE TABLE extended_data_objects (
          id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          isDeleted TINYINT(1) UNSIGNED NOT NULL,
          myColumn VARCHAR(255) NOT NULL,
          myNullableColumn INT(11) UNSIGNED NULL DEFAULT NULL,
          otherDataObjectId INT (11) UNSIGNED NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1');

        self::$connection->executeQuery('CREATE TABLE other_data_objects (
          id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          isDeleted TINYINT(1) UNSIGNED NOT NULL,
          `name` VARCHAR(255) NOT NULL,
          `extendedDataObjectId` INT (11) UNSIGNED NULL,
          FOREIGN KEY `extendedDataObjectId` (`extendedDataObjectId`) REFERENCES `extended_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1');

        self::$connection->executeQuery('CREATE TABLE extended_other_rel (
          extendedDataObjectId INT(11) UNSIGNED NOT NULL,
          otherDataObjectId INT(11) UNSIGNED NOT NULL,
          FOREIGN KEY `extendedId` (`extendedDataObjectId`) REFERENCES `extended_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
          FOREIGN KEY `otherId` (`otherDataObjectId`) REFERENCES `other_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
    }

    protected static function deleteDatabase()
    {
        self::$connection->executeQuery('DROP DATABASE corma_test');
    }
}
