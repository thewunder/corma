<?php
namespace Corma\Test\Repository;


use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\QueryHelper;
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
        $queryHelper = new QueryHelper(self::$connection, new ArrayCache());
        $this->dispatcher = new EventDispatcher();
        $this->repository = new ExtendedDataObjectRepository(self::$connection, $this->dispatcher, $queryHelper);
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

        $ids = ExtendedDataObject::getIds($objects);
        $ids[] = $object->getId();

        $fromDb = $this->repository->findByIds($ids);
        $this->assertCount(3, $fromDb);
    }

    public static function setUpBeforeClass()
    {
        $dotenv = new Dotenv(__DIR__.'/../../');
        $dotenv->load();
        if(empty($_ENV['MYSQL_HOST']) || empty($_ENV['MYSQL_USER']) || empty($_ENV['MYSQL_PASS'])) {
            throw new \RuntimeException('Create a .env file with MYSQL_HOST, MYSQL_USER, and MYSQL_PASS to run this test.');
        }
        $pdo = new \PDO('mysql:host='.$_ENV['MYSQL_HOST'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
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
