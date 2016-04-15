<?php
namespace Corma\Test\Integration;


use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;
use Corma\ObjectMapper;
use Corma\QueryHelper\MySQLQueryHelper;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MysqlIntegrationTest extends \PHPUnit_Framework_TestCase
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
        $queryHelper = new MySQLQueryHelper(self::$connection, $cache);
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
     * This one tests the MySQLQueryHelper implementation of massUpsert
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

        $inserts = $this->repository->saveAll($objects);

        $this->assertEquals(3, $inserts);

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
        $mySQLQueryHelper = new MySQLQueryHelper(self::$connection, $cache);
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
        $softDeleted = new OtherDataObject();
        $otherObjects[] = $softDeleted->setName('Other object (soft deleted)')->setExtendedDataObjectId($object->getId());
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-one 1')->setExtendedDataObjectId($object->getId());
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-one 2')->setExtendedDataObjectId($object->getId());
        $this->objectMapper->saveAll($otherObjects);

        $this->objectMapper->delete($softDeleted);

        /** @var OtherDataObject[] $loadedObjects */
        $loadedObjects = $this->repository->loadMany([$object], OtherDataObject::class);
        $this->assertCount(2, $loadedObjects);
        $this->assertInstanceOf(OtherDataObject::class, $loadedObjects[1]);

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
        if(empty(getenv('MYSQL_HOST')) && file_exists(__DIR__.'/../../.env')) {
            $dotenv = new Dotenv(__DIR__.'/../../');
            $dotenv->load();
        }

        if(empty(getenv('MYSQL_HOST')) || empty(getenv('MYSQL_USER'))) {
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

    public static function tearDownAfterClass()
    {
        self::$connection->query('DROP DATABASE corma_test');
    }
}
