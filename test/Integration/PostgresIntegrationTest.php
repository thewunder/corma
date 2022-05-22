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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Dotenv\Dotenv;

class PostgresIntegrationTest extends BaseIntegrationTest
{
    public function testIsDuplicateException()
    {
        $cache = new LimitedArrayCache();
        $mySQLQueryHelper = new PostgreSQLQueryHelper(self::$connection, $cache);

        $objectManagerFactory = ObjectManagerFactory::withDefaults($mySQLQueryHelper, Inflector::build(), $this->container);
        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($mySQLQueryHelper);
        $objectMapper->expects($this->any())->method('getIdentityMap')->willReturn(new LimitedArrayCache());
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $objectMapper, $cache, $this->dispatcher);

        $this->assertFalse($mySQLQueryHelper->isDuplicateException(new Exception()));

        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (Exception $e) {
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

        self::$connection = DriverManager::getConnection(['driver'=>'pdo_pgsql','host'=>getenv('PGSQL_HOST'), 'user'=> getenv('PGSQL_USER'), 'password'=>$pass]);
        try {
            self::$connection->executeQuery('drop schema cormatest cascade');
        } catch (Exception $e) {
        }

        self::$connection->executeQuery('create schema cormatest');
        self::$connection->executeQuery('SET search_path TO cormatest');
        self::$connection->executeQuery('CREATE TABLE cormatest.extended_data_objects (
          id SERIAL PRIMARY KEY,
          "isDeleted" BOOLEAN NOT NULL DEFAULT FALSE,
          "myColumn" VARCHAR(255) NOT NULL,
          "myNullableColumn" INT NULL DEFAULT NULL,
          "otherDataObjectId" INT NULL
        )');

        self::$connection->executeQuery('CREATE TABLE cormatest.other_data_objects (
          id SERIAL PRIMARY KEY,
          "isDeleted" BOOLEAN NOT NULL DEFAULT FALSE,
          "name" VARCHAR(255) NOT NULL,
          "extendedDataObjectId" INT NULL REFERENCES extended_data_objects (id)
        )');

        self::$connection->executeQuery('CREATE TABLE cormatest.extended_other_rel (
          "extendedDataObjectId" INT NOT NULL REFERENCES extended_data_objects (id),
          "otherDataObjectId" INT NOT NULL REFERENCES other_data_objects (id)
        )');
    }

    protected static function deleteDatabase()
    {
    }
}
