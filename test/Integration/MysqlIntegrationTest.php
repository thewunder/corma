<?php
namespace Corma\Test\Integration;

use Corma\DataObject\ObjectManagerFactory;
use Corma\ObjectMapper;
use Corma\QueryHelper\MySQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\Inflector;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Dotenv\Dotenv;
use Integration\BaseIntegrationTest;

class MysqlIntegrationTest extends BaseIntegrationTest
{
    public function testIsDuplicateException()
    {
        $cache = new ArrayCache();
        $mySQLQueryHelper = new MySQLQueryHelper(self::$connection, $cache);
        $objectManagerFactory = ObjectManagerFactory::withDefaults($mySQLQueryHelper, new Inflector());
        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $objectMapper, $cache, $this->dispatcher);

        $this->assertFalse($mySQLQueryHelper->isDuplicateException(new DBALException()));

        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (DBALException $e) {
            $this->assertTrue($mySQLQueryHelper->isDuplicateException($e));
            return;
        }

        $this->markTestIncomplete('Expected Exception was not thrown');
    }

    public function testUpsertWithoutPrimaryKey()
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

        $pdo = new \PDO('mysql:host='.getenv('MYSQL_HOST'), getenv('MYSQL_USER'), $pass);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        self::$connection = DriverManager::getConnection(['driver'=>'pdo_mysql','pdo'=>$pdo]);
        self::$connection->query('CREATE DATABASE corma_test DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;');
        self::$connection->query('USE corma_test');
        self::$connection->query('CREATE TABLE extended_data_objects (
          id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          isDeleted TINYINT(1) UNSIGNED NOT NULL,
          myColumn VARCHAR(255) NOT NULL,
          myNullableColumn INT(11) UNSIGNED NULL DEFAULT NULL,
          otherDataObjectId INT (11) UNSIGNED NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1');

        self::$connection->query('CREATE TABLE other_data_objects (
          id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          isDeleted TINYINT(1) UNSIGNED NOT NULL,
          `name` VARCHAR(255) NOT NULL,
          `extendedDataObjectId` INT (11) UNSIGNED NULL,
          FOREIGN KEY `extendedDataObjectId` (`extendedDataObjectId`) REFERENCES `extended_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1');

        self::$connection->query('CREATE TABLE extended_other_rel (
          extendedDataObjectId INT(11) UNSIGNED NOT NULL,
          otherDataObjectId INT(11) UNSIGNED NOT NULL,
          FOREIGN KEY `extendedId` (`extendedDataObjectId`) REFERENCES `extended_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
          FOREIGN KEY `otherId` (`otherDataObjectId`) REFERENCES `other_data_objects` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
    }

    protected static function deleteDatabase()
    {
        self::$connection->query('DROP DATABASE corma_test');
    }
}
