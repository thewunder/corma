<?php
namespace Corma\Test\Integration;

use Corma\DataObject\ObjectManagerFactory;
use Corma\Exception\MissingPrimaryKeyException;
use Corma\ObjectMapper;
use Corma\QueryHelper\PostgreSQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\Inflector;
use Corma\Util\LimitedArrayCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Dotenv\Dotenv;
use Integration\BaseIntegrationTest;

class PostgresIntegrationTest extends BaseIntegrationTest
{
    public function testIsDuplicateException()
    {
        $cache = new ArrayCache();
        $mySQLQueryHelper = new PostgreSQLQueryHelper(self::$connection, $cache);

        $objectManagerFactory = ObjectManagerFactory::withDefaults($mySQLQueryHelper, Inflector::build());
        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($mySQLQueryHelper);
        $objectMapper->expects($this->any())->method('getIdentityMap')->willReturn(new LimitedArrayCache());
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $objectMapper, $cache, $this->dispatcher);

        $this->assertFalse($mySQLQueryHelper->isDuplicateException(new DBALException()));

        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (DBALException $e) {
            $this->assertTrue($mySQLQueryHelper->isDuplicateException($e));
            $this->repository->deleteAll($this->repository->findAll());
            return;
        }

        $this->markTestIncomplete('Expected Exception was not thrown');
    }


    public function testUpsertWithoutPrimaryKey()
    {
        $this->expectException(MissingPrimaryKeyException::class);

        $object = new ExtendedDataObject();
        $object->setMyColumn('Upsert EDO');
        $this->objectMapper->save($object);

        $otherObject = new OtherDataObject();
        $otherObject->setName('Upsert ODO');
        $this->objectMapper->save($otherObject);

        $this->objectMapper->getQueryHelper()
            ->massUpsert('extended_other_rel', [['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=>$otherObject->getId()]]);
    }

    protected static function createDatabase()
    {
        if (empty(getenv('PGSQL_HOST')) && file_exists(__DIR__.'/../../.env')) {
            $dotenv = new Dotenv(__DIR__.'/../../');
            $dotenv->load();
        }

        if (empty(getenv('PGSQL_HOST')) || empty(getenv('PGSQL_USER'))) {
            throw new \RuntimeException('Create a .env file with PGSQL_HOST, PGSQL_USER, and PGSQL_PASS to run this test.');
        }

        $pass = getenv('PGSQL_PASS') ? getenv('PGSQL_PASS') : '';

        $pdo = new \PDO('pgsql:host='.getenv('PGSQL_HOST'), getenv('PGSQL_USER'), $pass);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        self::$connection = DriverManager::getConnection(['driver'=>'pdo_pgsql','pdo'=>$pdo, 'dbname'=>'public']);
        try {
            self::$connection->query('drop schema cormatest cascade');
        } catch (DBALException $e) {
        }

        self::$connection->query('create schema cormatest');
        self::$connection->query('CREATE TABLE extended_data_objects (
          id SERIAL PRIMARY KEY,
          "isDeleted" BOOLEAN NOT NULL DEFAULT FALSE,
          "myColumn" VARCHAR(255) NOT NULL,
          "myNullableColumn" INT NULL DEFAULT NULL,
          "otherDataObjectId" INT NULL
        )');

        self::$connection->query('CREATE TABLE other_data_objects (
          id SERIAL PRIMARY KEY,
          "isDeleted" BOOLEAN NOT NULL DEFAULT FALSE,
          "name" VARCHAR(255) NOT NULL,
          "extendedDataObjectId" INT NULL REFERENCES extended_data_objects (id)
        )');

        self::$connection->query('CREATE TABLE extended_other_rel (
          "extendedDataObjectId" INT NOT NULL REFERENCES extended_data_objects (id),
          "otherDataObjectId" INT NOT NULL REFERENCES other_data_objects (id)
        )');
    }

    protected static function deleteDatabase()
    {
    }
}
