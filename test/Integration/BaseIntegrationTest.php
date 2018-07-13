<?php
namespace Integration;

use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\ObjectMapper;
use Corma\Repository\ObjectRepositoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\PagedQuery;
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

    /** @var  ObjectIdentifierInterface */
    protected $identifier;

    /** @var Connection */
    protected static $connection;

    public function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $this->objectMapper = ObjectMapper::withDefaults(self::$connection);
        $this->identifier = $this->objectMapper->getObjectManagerFactory()->getIdentifier();
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

    protected static function createDatabase()
    {
    }
    
    protected static function deleteDatabase()
    {
    }
    
    abstract public function testIsDuplicateException();

    public function testCreate()
    {
        $object = $this->objectMapper->create(ExtendedDataObject::class, ['myColumn'=>'Test Value']);
        $this->assertInstanceOf(ExtendedDataObject::class, $object);
        $this->assertEquals('Test Value', $object->getMyColumn());
    }

    public function testSave()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('My Value')->setMyNullableColumn(15);
        $this->objectMapper->save($object);
        $this->assertNotNull($object->getId());
        return $object;
    }

    /**
     * @depends testSave
     * @param ExtendedDataObject $object
     */
    public function testFind(ExtendedDataObject $object)
    {
        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($object->getMyColumn(), $fromDb->getMyColumn());
        $this->assertEquals($object->getMyNullableColumn(), $fromDb->getMyNullableColumn());
    }

    public function testFindNull()
    {
        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find(12345678, false);
        $this->assertNull($fromDb);
    }

    /**
     * @depends testSave
     * @param ExtendedDataObject $object
     * @return ExtendedDataObject
     */
    public function testUpdate(ExtendedDataObject $object)
    {
        $object->setMyColumn('New Value')->setMyNullableColumn(null);
        $this->repository->save($object);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($object->getMyColumn(), $fromDb->getMyColumn());
        $this->assertNull($fromDb->getMyNullableColumn());
        return $object;
    }

    /**
     * @depends testUpdate
     * @param ExtendedDataObject $object
     */
    public function testDelete(ExtendedDataObject $object)
    {
        $this->repository->delete($object);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->findOneBy(['id'=>$object->getId(), 'isDeleted'=>1]);
        $this->assertTrue($fromDb->isDeleted());
    }

    /**
     * @depends testDelete
     * @return object[]
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

        $ids = $this->identifier->getIds($objects);
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

        foreach ($nullObjects as $object) {
            $this->assertNull($object->getMyNullableColumn());
        }
    }

    public function testFindByBool()
    {
        $object = new ExtendedDataObject();
        $object->setDeleted(true);
        $object->setMyColumn('Deleted');
        $this->repository->save($object);

        /** @var ExtendedDataObject[] $deletedObjects */
        $deletedObjects = $this->repository->findBy(['isDeleted'=>true]);
        $this->assertGreaterThan(0, $deletedObjects);

        foreach ($deletedObjects as $object) {
            $this->assertTrue($object->isDeleted());
        }
    }

    public function testFindByBetween()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('0');
        $objects[] = $object;
        $objects = [];
        $object = new ExtendedDataObject();
        $object->setMyColumn('1');
        $objects[] = $object;
        $object = new ExtendedDataObject();
        $object->setMyColumn('2');
        $objects[] = $object;
        $object = new ExtendedDataObject();
        $object->setMyColumn('3');
        $objects[] = $object;
        $object = new ExtendedDataObject();
        $object->setMyColumn('4');
        $objects[] = $object;
        $this->repository->saveAll($objects);

        /** @var ExtendedDataObject[] $betweenObjects */
        $betweenObjects = $this->repository->findBy(['myColumn BETWEEN'=>[1, 3]]);
        $this->assertCount(3, $betweenObjects);

        foreach ($betweenObjects as $i => $object) {
            $this->assertEquals($i+1, $object->getMyColumn());
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

        foreach ($notNullObjects as $object) {
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

        $allFromDb = $this->repository->findByIds($this->identifier->getIds($objects), false);
        $this->assertCount(2, $allFromDb);
    }

    public function testLoadOne()
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many');
        $this->objectMapper->save($otherObject);

        $object = new ExtendedDataObject();
        $object->setMyColumn('one-to-many')->setOtherDataObjectId($otherObject->getId());
        $this->objectMapper->save($object);

        $return = $this->objectMapper->loadOne([$object], OtherDataObject::class);

        $this->assertInstanceOf(OtherDataObject::class, $object->getOtherDataObject());
        $this->assertEquals('Other object one-to-many', $object->getOtherDataObject()->getName());
        $this->assertCount(1, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);
    }

    public function testLoadOneWithoutId()
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many');

        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object one-to-many 2');
        $this->objectMapper->saveAll([$otherObject, $otherObject2]);

        $object = new ExtendedDataObject();
        $object->setOtherDataObjectId($otherObject->getId());
        $object2 = new ExtendedDataObject();
        $object2->setOtherDataObjectId($otherObject2->getId());

        $return = $this->objectMapper->loadOne([$object, $object2], OtherDataObject::class);

        $this->assertInstanceOf(OtherDataObject::class, $object->getOtherDataObject());
        $this->assertEquals('Other object one-to-many', $object->getOtherDataObject()->getName());
        $this->assertInstanceOf(OtherDataObject::class, $object2->getOtherDataObject());
        $this->assertEquals('Other object one-to-many 2', $object2->getOtherDataObject()->getName());
        $this->assertCount(2, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject2->getId()]);
    }

    public function testLoadMany()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-one');
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
        $return = $this->objectMapper->loadMany([$object], OtherDataObject::class);
        $this->assertCount(2, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);

        $loadedOtherObjects = $object->getOtherDataObjects();
        $this->assertCount(2, $loadedOtherObjects);
        $this->assertEquals($otherObject->getId(), $loadedOtherObjects[1]->getId());
        $this->assertEquals($otherObject->getName(), $loadedOtherObjects[1]->getName());
    }

    public function testLoadManyWithCustomSetter()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-one');
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
        $return = $this->objectMapper->loadMany([$object], OtherDataObject::class, null, 'setCustom');
        $this->assertCount(2, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);

        $loadedOtherObjects = $object->getCustom();
        $this->assertCount(2, $loadedOtherObjects);
        $this->assertEquals($otherObject->getId(), $loadedOtherObjects[1]->getId());
        $this->assertEquals($otherObject->getName(), $loadedOtherObjects[1]->getName());
    }
    
    public function testLoadManyWithoutObjects()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-one');
        $this->repository->save($object);

        /** @var OtherDataObject[] $return */
        $return = $this->objectMapper->loadMany([$object], OtherDataObject::class);
        $this->assertCount(0, $return);

        $loadedOtherObjects = $object->getOtherDataObjects();
        $this->assertTrue(is_array($loadedOtherObjects));
        $this->assertCount(0, $loadedOtherObjects);
    }

    public function testLoadManyToMany()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-many');
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

        $return = $this->objectMapper->loadManyToMany([$object], OtherDataObject::class, 'extended_other_rel');
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
        $object3 = new ExtendedDataObject();
        $objects[] = $object3->setMyColumn('Save one-to-one 3');

        $this->repository->saveAll($objects);
        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveOne($objects, OtherDataObject::class);

        $this->assertGreaterThan(0, $otherObject->getId());
        $this->assertGreaterThan(0, $otherObject2->getId());
        $this->assertEquals($otherObject->getId(), $object->getOtherDataObjectId());
        $this->assertEquals($otherObject2->getId(), $object2->getOtherDataObjectId());
        $this->assertEquals($object->getId(), $otherObject->getExtendedDataObjectId());
        $this->assertEquals($object2->getId(), $otherObject2->getExtendedDataObjectId());
        $this->assertNull($object3->getOtherDataObjectId());

        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($otherObject->getId(), $fromDb->getOtherDataObjectId());
    }

    public function testSaveMany()
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many 1-1');
        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object one-to-many 1-2');

        $otherObject3 = new OtherDataObject();
        $otherObject3->setName('Other object one-to-many 2-1');
        $otherObject4 = new OtherDataObject();
        $otherObject4->setName('Other object one-to-many 2-2');

        $otherObjectToDelete = new OtherDataObject();
        $otherObjectToDelete->setName('Other object one-to-many 1-3');
        $otherObjectToDelete2 = new OtherDataObject();
        $otherObjectToDelete2->setName('Other object one-to-many 2-3');

        $objects = [];
        $object = new ExtendedDataObject();
        $object2 = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save one-to-many 1')->setOtherDataObjects([$otherObject, $otherObject2]);
        $objects[] = $object2->setMyColumn('Save one-to-many 2')->setOtherDataObjects([$otherObject3, $otherObject4]);

        $this->repository->saveAll($objects);
        
        $otherObjectToDelete->setExtendedDataObjectId($object->getId());
        $otherObjectToDelete2->setExtendedDataObjectId($object2->getId());
        $this->objectMapper->saveAll([$otherObjectToDelete, $otherObjectToDelete2]);

        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveMany($objects, OtherDataObject::class);

        $this->assertGreaterThan(0, $otherObject->getId());
        $this->assertGreaterThan(0, $otherObject2->getId());
        $this->assertGreaterThan(0, $otherObject3->getId());
        $this->assertGreaterThan(0, $otherObject4->getId());
        $this->assertEquals($object->getId(), $otherObject->getExtendedDataObjectId());
        $this->assertEquals($object->getId(), $otherObject2->getExtendedDataObjectId());
        $this->assertEquals($object2->getId(), $otherObject3->getExtendedDataObjectId());
        $this->assertEquals($object2->getId(), $otherObject4->getExtendedDataObjectId());

        $otherObjectToDelete = $this->objectMapper->findOneBy(OtherDataObject::class, ['id'=>$otherObjectToDelete->getId(), 'isDeleted'=>1]);
        $otherObjectToDelete2 = $this->objectMapper->findOneBy(OtherDataObject::class, ['id'=>$otherObjectToDelete2->getId(), 'isDeleted'=>1]);
        $this->assertTrue($otherObjectToDelete->isDeleted());
        $this->assertTrue($otherObjectToDelete2->isDeleted());
    }

    public function testSaveManyMove()
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many 1-1');
        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object one-to-many 1-2');

        $otherObject3 = new OtherDataObject();
        $otherObject3->setName('Other object one-to-many 2-1');
        $otherObject4 = new OtherDataObject();
        $otherObject4->setName('Other object one-to-many 2-2');

        $otherObjectToMove = new OtherDataObject();
        $otherObjectToMove->setName('Other object one-to-many move');

        $objects = [];
        $object = new ExtendedDataObject();
        $object2 = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save one-to-many 1')->setOtherDataObjects([$otherObject, $otherObject2, $otherObjectToMove]);
        $objects[] = $object2->setMyColumn('Save one-to-many 2')->setOtherDataObjects([$otherObject3, $otherObject4]);

        $this->repository->saveAll($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveMany($objects, OtherDataObject::class);

        $this->assertEquals($object->getId(), $otherObjectToMove->getExtendedDataObjectId());
        $others1 = $object->getOtherDataObjects();
        unset($others1[2]);
        $object->setOtherDataObjects($others1);
        $others2 = $object2->getOtherDataObjects();
        $others2[] = $otherObjectToMove;
        $object2->setOtherDataObjects($others2);

        $relationshipSaver->saveMany($objects, OtherDataObject::class);

        $otherObjectToMove = $this->objectMapper->find(OtherDataObject::class, $otherObjectToMove->getId(), false);
        $this->assertEquals($object2->getId(), $otherObjectToMove->getExtendedDataObjectId());
        $this->assertFalse($otherObjectToMove->isDeleted());

        $others1[] = $otherObjectToMove;
        $object->setOtherDataObjects($others1);
        unset($others2[2]);
        $object2->setOtherDataObjects($others2);

        $relationshipSaver->saveMany($objects, OtherDataObject::class);

        $otherObjectToMove = $this->objectMapper->find(OtherDataObject::class, $otherObjectToMove->getId(), false);
        $this->assertEquals($object->getId(), $otherObjectToMove->getExtendedDataObjectId());
        $this->assertFalse($otherObjectToMove->isDeleted());
    }

    public function testSaveManyToManyLinks()
    {
        $otherObjects = [];
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-many 1-1');
        $otherObject2 = new OtherDataObject();
        $otherObjects[] = $otherObject2->setName('Other object many-to-many 1-2');

        $otherObject3 = new OtherDataObject();
        $otherObjects[] = $otherObject3->setName('Other object many-to-many 2-1');
        $otherObject4 = new OtherDataObject();
        $otherObjects[] = $otherObject4->setName('Other object many-to-many 2-2');

        $otherObjectToDelete = new OtherDataObject();
        $otherObjects[] = $otherObjectToDelete->setName('Other object many-to-many 1-3');
        $otherObjectToDelete2 = new OtherDataObject();
        $otherObjects[] = $otherObjectToDelete2->setName('Other object many-to-many 2-3');

        $objects = [];
        $object = new ExtendedDataObject();
        $object2 = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save many-to-many 1')->setOtherDataObjects([$otherObject, $otherObject2]);
        $objects[] = $object2->setMyColumn('Save many-to-many 2')->setOtherDataObjects([$otherObject3, $otherObject4]);

        $this->repository->saveAll($objects);

        $this->objectMapper->saveAll($otherObjects);

        $queryHelper = $this->objectMapper->getQueryHelper();
        $queryHelper->massInsert('extended_other_rel', [
            ['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=> $otherObjectToDelete->getId()],
            ['extendedDataObjectId'=>$object2->getId(), 'otherDataObjectId'=> $otherObjectToDelete2->getId()],
        ]);

        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveManyToManyLinks($objects, OtherDataObject::class, 'extended_other_rel');

        $objectLinks = $queryHelper->buildSelectQuery('extended_other_rel', self::$connection->quoteIdentifier('otherDataObjectId'), ['extendedDataObjectId'=>$object->getId()])
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertCount(2, $objectLinks);
        $this->assertEquals($otherObject->getId(), $objectLinks[0]);
        $this->assertEquals($otherObject2->getId(), $objectLinks[1]);

        $object2Links = $queryHelper->buildSelectQuery('extended_other_rel', self::$connection->quoteIdentifier('otherDataObjectId'), ['extendedDataObjectId'=>$object2->getId()])
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertCount(2, $object2Links);
        $this->assertEquals($otherObject3->getId(), $object2Links[0]);
        $this->assertEquals($otherObject4->getId(), $object2Links[1]);
    }

    public function testSaveManyToMany()
    {
        $otherObjects = [];
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object many-to-many 1-1');
        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object many-to-many 1-2');

        $otherObject3 = new OtherDataObject();
        $otherObject3->setName('Other object many-to-many 2-1');
        $otherObject4 = new OtherDataObject();
        $otherObject4->setName('Other object many-to-many 2-2');

        $otherObjectToDelete = new OtherDataObject();
        $otherObjects[] = $otherObjectToDelete->setName('Other object many-to-many 1-3');
        $otherObjectToDelete2 = new OtherDataObject();
        $otherObjects[] = $otherObjectToDelete2->setName('Other object many-to-many 2-3');

        $objects = [];
        $object = new ExtendedDataObject();
        $object2 = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save many-to-many 1')->setOtherDataObjects([$otherObject, $otherObject2]);
        $objects[] = $object2->setMyColumn('Save many-to-many 2')->setOtherDataObjects([$otherObject3, $otherObject4]);

        $this->repository->saveAll($objects);

        $this->objectMapper->saveAll($otherObjects);

        $queryHelper = $this->objectMapper->getQueryHelper();
        $queryHelper->massInsert('extended_other_rel', [
            ['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=> $otherObjectToDelete->getId()],
            ['extendedDataObjectId'=>$object2->getId(), 'otherDataObjectId'=> $otherObjectToDelete2->getId()],
        ]);

        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveManyToMany($objects, OtherDataObject::class, 'extended_other_rel');

        $this->assertGreaterThan(0, $otherObject->getId());
        $this->assertGreaterThan(0, $otherObject2->getId());
        $this->assertGreaterThan(0, $otherObject3->getId());
        $this->assertGreaterThan(0, $otherObject4->getId());

        $objectLinks = $queryHelper->buildSelectQuery('extended_other_rel', self::$connection->quoteIdentifier('otherDataObjectId'), ['extendedDataObjectId'=>$object->getId()])
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertCount(2, $objectLinks);
        $this->assertEquals($otherObject->getId(), $objectLinks[0]);
        $this->assertEquals($otherObject2->getId(), $objectLinks[1]);

        $object2Links = $queryHelper->buildSelectQuery('extended_other_rel', self::$connection->quoteIdentifier('otherDataObjectId'), ['extendedDataObjectId'=>$object2->getId()])
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertCount(2, $object2Links);
        $this->assertEquals($otherObject3->getId(), $object2Links[0]);
        $this->assertEquals($otherObject4->getId(), $object2Links[1]);
    }

    public function testPagedQuery()
    {
        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $pager = $repo->findAllPaged();
        $this->assertInstanceOf(PagedQuery::class, $pager);

        $this->assertGreaterThan(1, $pager->getPages());

        for ($i = 1; $i <= $pager->getPages(); $i++) {
            $objects = $pager->getResults($i);
            $this->assertLessThanOrEqual(5, count($objects));
            $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        }
    }
}
