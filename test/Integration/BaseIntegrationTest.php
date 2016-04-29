<?php
namespace Integration;

use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;
use Corma\ObjectMapper;
use Corma\Repository\ObjectRepositoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class BaseIntegrationTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ObjectRepositoryInterface */
    protected $repository;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var ObjectMapper */
    protected $objectMapper;

    /** @var Connection */
    protected static $connection;
    
    public function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $this->objectMapper = ObjectMapper::withDefaults(self::$connection, ['Corma\\Test\\Fixtures']);
        $this->repository = $this->objectMapper->getRepository(ExtendedDataObject::class);
    }
    
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::createDatabase();
    }
    
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::deleteDatabase();
    }

    protected abstract function createDatabase();
    
    protected abstract function deleteDatabase();
    
    public abstract function testIsDuplicateException();

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
        $this->assertGreaterThanOrEqual(2, count($objects));

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
        $this->assertCount(count($objects) + 1, $fromDb);
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
        $withIdsGt = $this->repository->findBy(['id >'=>$object->getId(), 'isDeleted'=>0]);
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

    public function testLoadOne()
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many');
        $this->objectMapper->save($otherObject);

        $object = new ExtendedDataObject();
        $object->setMyColumn('one-to-many')->setOtherDataObjectId($otherObject->getId());
        $this->repository->save($object);

        $return = $this->repository->loadOne([$object], OtherDataObject::class);

        $this->assertInstanceOf(OtherDataObject::class, $object->getOtherDataObject());
        $this->assertEquals('Other object one-to-many', $object->getOtherDataObject()->getName());
        $this->assertCount(1, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);
    }

    public function testLoadMany()
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

        /** @var OtherDataObject[] $return */
        $return = $this->repository->loadMany([$object], OtherDataObject::class);
        $this->assertCount(2, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);

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

        $return = $this->repository->loadManyToMany([$object], OtherDataObject::class, 'extended_other_rel');
        $this->assertCount(2, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);

        $loadedOtherObjects = $object->getOtherDataObjects();
        $this->assertCount(2, $loadedOtherObjects);
        $this->assertEquals($otherObject2->getId(), $loadedOtherObjects[1]->getId());
        $this->assertEquals($otherObject2->getName(), $loadedOtherObjects[1]->getName());
    }

    public function testSaveOne()
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-one 1');
        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object one-to-one 2');

        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save one-to-one 1')->setOtherDataObject($otherObject);
        $object2 = new ExtendedDataObject();
        $objects[] = $object2->setMyColumn('Save one-to-one 2')->setOtherDataObject($otherObject2);

        $this->repository->saveAll($objects);
        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveOne($objects, 'otherDataObjectId');

        $this->assertGreaterThan(0, $otherObject->getId());
        $this->assertGreaterThan(0, $otherObject2->getId());
        $this->assertEquals($otherObject->getId(), $object->getOtherDataObjectId());
        $this->assertEquals($otherObject2->getId(), $object2->getOtherDataObjectId());
        $this->assertEquals($object->getId(), $otherObject->getExtendedDataObjectId());
        $this->assertEquals($object2->getId(), $otherObject2->getExtendedDataObjectId());

        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($otherObject->getId(), $fromDb->getOtherDataObjectId());
    }
}