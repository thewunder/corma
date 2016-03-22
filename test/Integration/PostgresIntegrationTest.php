<?php
namespace Corma\Test\Integration;


use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;
use Corma\ObjectMapper;
use Corma\QueryHelper\PostgreSQLQueryHelper;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\QueryHelper\QueryHelper;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PostgresIntegrationTest extends \PHPUnit_Framework_TestCase
{
    /** @var ExtendedDataObjectRepository */
    private $repository;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var ObjectMapper */
    private $objectMapper;

    /** @var Connection */
    private static $connection;

    public function setUp()
    {
        $cache = new ArrayCache();
        $queryHelper = new PostgreSQLQueryHelper(self::$connection, $cache);
        $this->dispatcher = new EventDispatcher();

        $repositoryFactory = new ObjectRepositoryFactory(['Corma\\Test\\Fixtures']);
        $this->objectMapper = new ObjectMapper($queryHelper, $repositoryFactory);
        $repositoryFactory->setDependencies([self::$connection, $this->dispatcher, $this->objectMapper, $cache]);

        $this->repository = new ExtendedDataObjectRepository(self::$connection, $this->dispatcher, $this->objectMapper, $cache);
    }

    public function testSaveAndFind()
    {
        $object = new ExtendedDataObject($this->dispatcher);
        $object->setMyColumn('My Value')->setMyNullableColumn(15);
        $this->repository->save($object);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($object->getMyColumn(), $fromDb->getMyColumn());
        $this->assertEquals($object->getMyNullableColumn(), $fromDb->getMyNullableColumn());

        return $object;
    }

    /**
     * @depends testSaveAndFind
     * @param ExtendedDataObject $object
     * @return ExtendedDataObject
     */
    public function testUpdate(ExtendedDataObject $object)
    {
        $object->setMyColumn('New Value');
        $this->repository->save($object);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($object->getMyColumn(), $fromDb->getMyColumn());
        return $object;
    }

    /**
     * @depends testUpdate
     * @param ExtendedDataObject $object
     */
    public function testDelete(ExtendedDataObject $object)
    {
        $this->repository->delete($object);
        $this->assertTrue($object->isDeleted());

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertTrue($fromDb->isDeleted());
    }

    /**
     * @depends testDelete
     * @return \Corma\DataObject\DataObjectInterface[]
     */
    public function testFindAll()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF');
        $this->repository->save($object);
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF 2');
        $this->repository->save($object);

        $objects = $this->repository->findAll();
        $this->assertCount(2, $objects);

        return $objects;
    }

    /**
     * @depends testFindAll
     * @param array $objects
     */
    public function testFindByIds(array $objects)
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF 3');
        $this->repository->save($object);

        $this->repository->find($object->getId());

        $ids = ExtendedDataObject::getIds($objects);
        $ids[] = $object->getId();

        $fromDb = $this->repository->findByIds($ids);
        $this->assertCount(3, $fromDb);
    }

    public function testFindBy()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF 4');
        $this->repository->save($object);
        $object2 = new ExtendedDataObject();
        $object2->setMyColumn('XYZ');
        $this->repository->save($object2);

        /** @var ExtendedDataObject[] $fromDb */
        $fromDb = $this->repository->findBy(['myColumn'=>['ASDF 4', 'XYZ']], ['myColumn'=>'DESC']);
        $this->assertCount(2, $fromDb);
        $this->assertEquals('XYZ', $fromDb[0]->getMyColumn());
        $this->assertEquals('ASDF 4', $fromDb[1]->getMyColumn());

        /** @var ExtendedDataObject[] $limited */
        $limited = $this->repository->findBy(['myColumn'=>['ASDF 4', 'XYZ']], ['myColumn'=>'DESC'], 1, 1);
        $this->assertCount(1, $limited);
        $this->assertEquals('ASDF 4', $limited[0]->getMyColumn());

        /** @var ExtendedDataObject[] $withIdsGt */
        $withIdsGt = $this->repository->findBy(['id >'=>$object->getId()]);
        $this->assertCount(1, $withIdsGt);
        $this->assertEquals('XYZ', $withIdsGt[0]->getMyColumn());
    }

    public function testFindByNull()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF 4');
        $this->repository->save($object);

        /** @var ExtendedDataObject[] $nullObjects */
        $nullObjects = $this->repository->findBy(['myNullableColumn'=>null]);
        $this->assertGreaterThan(0, $nullObjects);

        foreach($nullObjects as $object) {
            $this->assertNull($object->getMyNullableColumn());
        }
    }

    public function testFindByIsNotNull()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF 4')->setMyNullableColumn(42);
        $this->repository->save($object);

        /** @var ExtendedDataObject[] $notNullObjects */
        $notNullObjects = $this->repository->findBy(['myNullableColumn !='=>null]);
        $this->assertGreaterThan(0, $notNullObjects);

        foreach($notNullObjects as $object) {
            $this->assertNotNull($object->getMyNullableColumn());
        }
    }

    public function testFindOneBy()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('XYZ 2');
        $this->repository->save($object);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->findOneBy(['myColumn'=>'XYZ 2']);
        $this->assertEquals('XYZ 2', $fromDb->getMyColumn());
    }

    /**
     * This one tests the default QueryHelper implementation of massUpsert
     */
    public function testSaveAll()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Save All');
        $this->repository->save($object);
        $object->setMyColumn('Save All Updated');

        $objects = [$object];
        $object2 = new ExtendedDataObject();
        $objects[] = $object2->setMyColumn('Save All 2');

        $object3 = new ExtendedDataObject();
        $objects[] = $object3->setMyColumn('Save All 3');

        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cache = new ArrayCache();
        $objectMapper->expects($this->any())->method('getQueryHelper')->willReturn(new PostgreSQLQueryHelper(self::$connection, $cache));

        $this->repository = new ExtendedDataObjectRepository(self::$connection, $this->dispatcher, $objectMapper, $cache);

        $effected = $this->repository->saveAll($objects);

        $this->assertEquals(3, $effected);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals('Save All Updated', $fromDb->getMyColumn());

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object2->getId(), false);
        $this->assertEquals('Save All 2', $fromDb->getMyColumn());
    }

    public function testDeleteAll()
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] =$object->setMyColumn('deleteAll 1');
        $this->repository->save($object);

        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('deleteAll 2');
        $this->repository->save($object);

        $rows = $this->repository->deleteAll($objects);
        $this->assertEquals(2, $rows);

        $allFromDb = $this->repository->findByIds(DataObject::getIds($objects), false);
        $this->assertCount(2, $allFromDb);
        /** @var DataObjectInterface $objectFromDb */
        foreach($allFromDb as $objectFromDb) {
            $this->assertTrue($objectFromDb->isDeleted());
        }
    }

    public function testIsDuplicateException()
    {
        $cache = new ArrayCache();
        $mySQLQueryHelper = new PostgreSQLQueryHelper(self::$connection, $cache);
        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($mySQLQueryHelper);
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $this->dispatcher, $objectMapper, $cache);

        $this->assertFalse($mySQLQueryHelper->isDuplicateException(new DBALException()));

        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (DBALException $e) {
            $this->assertTrue($mySQLQueryHelper->isDuplicateException($e));
            return;
        }

        $this->markTestIncomplete('Expected Exception was not thrown');
    }

    public function testLoadOneToMany()
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many');
        $this->objectMapper->save($otherObject);

        $object = new ExtendedDataObject();
        $object->setMyColumn('one-to-many')->setOtherDataObjectId($otherObject->getId());
        $this->repository->save($object);

        $this->repository->loadOne([$object], OtherDataObject::class);

        $this->assertInstanceOf(OtherDataObject::class, $object->getOtherDataObject());
        $this->assertEquals('Other object one-to-many', $object->getOtherDataObject()->getName());
    }

    public function testLoadManyToOne()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-one');;
        $this->repository->save($object);

        $otherObjects = [];
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-one 1')->setExtendedDataObjectId($object->getId());
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-one 2')->setExtendedDataObjectId($object->getId());
        $this->objectMapper->saveAll($otherObjects);

        $this->repository->loadMany([$object], OtherDataObject::class);

        $loadedOtherObjects = $object->getOtherDataObjects();
        $this->assertCount(2, $loadedOtherObjects);
        $this->assertEquals($otherObject->getId(), $loadedOtherObjects[1]->getId());
        $this->assertEquals($otherObject->getName(), $loadedOtherObjects[1]->getName());
    }

    public function testLoadManyToMany()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-many');;
        $this->repository->save($object);

        $otherObjects = [];
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-many 1')->setExtendedDataObjectId($object->getId());
        $otherObject2 = new OtherDataObject();
        $otherObjects[] = $otherObject2->setName('Other object many-to-many 2')->setExtendedDataObjectId($object->getId());
        $this->objectMapper->saveAll($otherObjects);

       $this->objectMapper->getQueryHelper()->massInsert('extended_other_rel', [
            ['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=>$otherObject->getId()],
            ['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=>$otherObject2->getId()]
        ]);

        $this->repository->loadManyToMany([$object], OtherDataObject::class, 'extended_other_rel');

        $loadedOtherObjects = $object->getOtherDataObjects();
        $this->assertCount(2, $loadedOtherObjects);
        $this->assertEquals($otherObject2->getId(), $loadedOtherObjects[1]->getId());
        $this->assertEquals($otherObject2->getName(), $loadedOtherObjects[1]->getName());
    }

    public static function setUpBeforeClass()
    {
        if(empty(getenv('PGSQL_HOST')) && file_exists(__DIR__.'/../../.env')) {
            $dotenv = new Dotenv(__DIR__.'/../../');
            $dotenv->load();
        }

        if(empty(getenv('PGSQL_HOST')) || empty(getenv('PGSQL_USER'))) {
            throw new \RuntimeException('Create a .env file with PGSQL_HOST, PGSQL_USER, and PGSQL_PASS to run this test.');
        }

        $pass = getenv('PGSQL_PASS') ? getenv('PGSQL_PASS') : '';

        $pdo = new \PDO('pgsql:host='.getenv('PGSQL_HOST'), getenv('PGSQL_USER'), $pass);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        self::$connection = DriverManager::getConnection(['driver'=>'pdo_pgsql','pdo'=>$pdo]);
        self::$connection->query('drop schema public cascade');
        self::$connection->query('create schema public');
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
}
