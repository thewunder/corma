<?php
namespace Corma\Test\Repository;


use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;
use Corma\QueryHelper\MySQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\QueryHelper\QueryHelper;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
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

    /** @var Connection */
    private static $connection;

    public function setUp()
    {
        $cache = new ArrayCache();
        $queryHelper = new QueryHelper(self::$connection, $cache);
        $this->dispatcher = new EventDispatcher();
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $this->dispatcher, $queryHelper, $cache);
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
        $this->assertTrue($object->getIsDeleted());

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertTrue($fromDb->getIsDeleted());
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
        $object = new ExtendedDataObject();
        $object->setMyColumn('XYZ');
        $this->repository->save($object);

        /** @var ExtendedDataObject[] $fromDb */
        $fromDb = $this->repository->findBy(['myColumn'=>['ASDF 4', 'XYZ']], ['myColumn'=>'DESC']);
        $this->assertCount(2, $fromDb);
        $this->assertEquals('XYZ', $fromDb[0]->getMyColumn());
        $this->assertEquals('ASDF 4', $fromDb[1]->getMyColumn());

        /** @var ExtendedDataObject[] $limited */
        $limited = $this->repository->findBy(['myColumn'=>['ASDF 4', 'XYZ']], ['myColumn'=>'DESC'], 1, 1);
        $this->assertCount(1, $limited);
        $this->assertEquals('ASDF 4', $limited[0]->getMyColumn());
    }

    public function testFindByNull()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF 4');
        $this->repository->save($object);

        /** @var ExtendedDataObject[] $limited */
        $nullObjects = $this->repository->findBy(['myNullableColumn'=>null]);
        $this->assertGreaterThan(0, $nullObjects);
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

        $effected = $this->repository->saveAll($objects);

        $this->assertEquals(3, $effected);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals('Save All Updated', $fromDb->getMyColumn());
    }

    /**
     * This one tests the MySQLQueryHelper implementation of massUpsert
     */
    public function testSaveAllOnDuplicateKey()
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

        $cache = new ArrayCache();
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $this->dispatcher, new MySQLQueryHelper(self::$connection, $cache), $cache);

        $inserts = $this->repository->saveAll($objects);

        $this->assertEquals(3, $inserts);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals('Save All Updated', $fromDb->getMyColumn());
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
            $this->assertTrue($objectFromDb->getIsDeleted());
        }
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
          myNullableColumn INT(11) UNSIGNED NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1');
    }

    public static function tearDownAfterClass()
    {
        self::$connection->query('DROP DATABASE corma_test');
    }
}
