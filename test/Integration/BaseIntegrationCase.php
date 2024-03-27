<?php
namespace Corma\Test\Integration;

use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\ObjectMapper;
use Corma\Repository\ObjectRepositoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\OffsetPagedQuery;
use Corma\Util\SeekPagedQuery;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class BaseIntegrationCase extends TestCase
{
    protected ObjectRepositoryInterface $repository;
    protected EventDispatcher $dispatcher;
    protected ObjectMapper $objectMapper;
    protected ObjectIdentifierInterface $identifier;
    protected ContainerInterface|MockObject $container;

    protected static Connection $connection;

    public function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $this->container->method('get')->willReturnCallback(fn(string $className) => new $className());
        $this->objectMapper = ObjectMapper::withDefaults(self::$connection, $this->container);
        $this->identifier = $this->objectMapper->getObjectManagerFactory()->getIdentifier();
        $this->repository = $this->objectMapper->getRepository(ExtendedDataObject::class);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::createDatabase();
    }

    public static function tearDownAfterClass(): void
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

    public function testCreate(): void
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

    #[Depends('testSave')]
    public function testFind(ExtendedDataObject $object): void
    {
        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($object->getMyColumn(), $fromDb->getMyColumn());
        $this->assertEquals($object->getMyNullableColumn(), $fromDb->getMyNullableColumn());
    }

    public function testFindNull(): void
    {
        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find(12345678, false);
        $this->assertNull($fromDb);
    }

    /**
     * @return ExtendedDataObject
     */
    #[Depends('testSave')]
    public function testUpdate(ExtendedDataObject $object): ExtendedDataObject
    {
        $object->setMyColumn('New Value')->setMyNullableColumn(null);
        $this->repository->save($object);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals($object->getMyColumn(), $fromDb->getMyColumn());
        $this->assertNull($fromDb->getMyNullableColumn());
        return $object;
    }

    #[Depends('testUpdate')]
    public function testDelete(ExtendedDataObject $object): void
    {
        $this->repository->delete($object);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->findOneBy(['id'=>$object->getId(), 'isDeleted'=>1]);
        $this->assertTrue($fromDb->isDeleted());
    }

    /**
     * @return object[]
     */
    #[Depends('testDelete')]
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

    #[Depends('testFindAll')]
    public function testFindByIds(array $objects): void
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

    public function testFindBy(): void
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

    public function testFindByNull(): void
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

    public function testFindByNullWithTableAlias(): void
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('ASDF 4');
        $this->repository->save($object);

        /** @var ExtendedDataObject[] $nullObjects */
        $nullObjects = $this->repository->findBy(['main.myNullableColumn'=>null]);
        $this->assertGreaterThan(0, $nullObjects);

        foreach ($nullObjects as $object) {
            $this->assertNull($object->getMyNullableColumn());
        }
    }

    public function testFindByBool(): void
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

    public function testFindByBetween(): void
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

    public function testFindByIsNotNull(): void
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

    public function testFindOneBy(): void
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
    public function testSaveAll(): void
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

    public function testSaveAllWithDuplicates(): void
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Save All');
        $this->repository->save($object);
        $object->setMyColumn('Save All Updated');

        $objects = [$object];
        $object2 = new ExtendedDataObject();
        $objects[] = $object2->setMyColumn('Save All 2');
        $objects[] = $object;
        $objects[] = $object2;

        $inserts = $this->repository->saveAll($objects);

        $this->assertEquals(2, $inserts);

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertEquals('Save All Updated', $fromDb->getMyColumn());

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->repository->find($object2->getId(), false);
        $this->assertEquals('Save All 2', $fromDb->getMyColumn());
    }

    public function testDeleteAll(): void
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

    public function testLoadOne(): void
    {
        $objects = $this->setupLoadOne();

        $return = $this->objectMapper->load($objects, 'otherDataObject');

        $this->assertLoadOne($objects, $return);
    }

    public function testLoadOneLegacy(): void
    {
        $objects = $this->setupLoadOne();

        $return = $this->objectMapper->loadOne($objects, OtherDataObject::class);

        $this->assertLoadOne($objects, $return);
    }

    private function setupLoadOne(): array
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many');
        $this->objectMapper->save($otherObject);

        $object = new ExtendedDataObject();
        $object->setMyColumn('one-to-many')->setOtherDataObjectId($otherObject->getId());
        $this->objectMapper->save($object);
        return [$object];
    }


    private function assertLoadOne(array $objects, array $return): void
    {
        $object = $objects[0];
        $otherObject = $object->getOtherDataObject();

        $this->assertInstanceOf(OtherDataObject::class, $object->getOtherDataObject());
        $this->assertEquals('Other object one-to-many', $object->getOtherDataObject()->getName());
        $this->assertCount(1, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);
    }

    public function testLoadOneWithoutId(): void
    {
        $objects = $this->setupLoadOneWithoutId();

        $return = $this->objectMapper->load($objects, 'otherDataObject');

        $this->assertLoadOneWithoutId($objects, $return);
    }

    public function testLoadOneWithoutIdLegacy(): void
    {
        $objects = $this->setupLoadOneWithoutId();

        $return = $this->objectMapper->loadOne($objects, OtherDataObject::class);

        $this->assertLoadOneWithoutId($objects, $return);
    }

    private function setupLoadOneWithoutId(): array
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
        return [$object, $object2];
    }

    private function assertLoadOneWithoutId(array $objects, array $return): void
    {
        [$object, $object2] = $objects;
        $otherObject = $object->getOtherDataObject();
        $otherObject2 = $object2->getOtherDataObject();

        $this->assertInstanceOf(OtherDataObject::class, $object->getOtherDataObject());
        $this->assertEquals('Other object one-to-many', $object->getOtherDataObject()->getName());
        $this->assertInstanceOf(OtherDataObject::class, $object2->getOtherDataObject());
        $this->assertEquals('Other object one-to-many 2', $object2->getOtherDataObject()->getName());
        $this->assertCount(2, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject2->getId()]);
    }

    public function testLoadOneWithNull(): void
    {
        $objects = $this->setupLoadOneWithNull();

        $return = $this->objectMapper->load($objects, 'otherDataObject');

        $this->assertLoadOneWithNull($objects, $return);
    }

    public function testLoadOneWithNullLegacy(): void
    {
        $objects = $this->setupLoadOneWithNull();

        $return = $this->objectMapper->loadOne($objects, OtherDataObject::class);

        $this->assertLoadOneWithNull($objects, $return);
    }

    private function setupLoadOneWithNull(): array
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-one');
        $this->objectMapper->save($otherObject);

        $object = new ExtendedDataObject();
        $object->setOtherDataObjectId($otherObject->getId());
        $object2 = new ExtendedDataObject();
        $this->objectMapper->saveAll([$object, $object2]);
        return [$object, $object2];
    }

    private function assertLoadOneWithNull(array $objects, array $return): void
    {
        [$object, $object2] = $objects;
        $otherObject = $object->getOtherDataObject();

        $this->assertInstanceOf(OtherDataObject::class, $otherObject);
        $this->assertEquals('Other object one-to-one', $otherObject->getName());
        $this->assertNull($object2->getOtherDataObject());
        $this->assertCount(1, $return);
        $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);
    }

    public function testLoadMany(): void
    {
        $object = $this->setupLoadMany();

        /** @var OtherDataObject[] $return */
        $return = $this->objectMapper->load([$object], 'otherDataObjects');
        $this->assertLoadMany($object, $return);
    }

    public function testLoadManyWithCustomSetter(): void
    {
        $object = $this->setupLoadMany();

        /** @var OtherDataObject[] $return */
        $return = $this->objectMapper->loadMany([$object], OtherDataObject::class, null, 'setCustoms');

        $this->assertLoadMany($object, $return, 'getCustoms');
    }

    private function setupLoadMany(): ExtendedDataObject
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
        return $object;
    }

    private function assertLoadMany(ExtendedDataObject $object, array $return, string $getter = 'getOtherDataObjects'): void
    {
        $this->assertCount(2, $return);

        $loadedOtherObjects = $object->$getter();
        $this->assertCount(2, $loadedOtherObjects);
        foreach ($loadedOtherObjects as $otherObject) {
            $this->assertInstanceOf(OtherDataObject::class, $return[$otherObject->getId()]);
            $this->assertEquals($otherObject->getId(), $return[$otherObject->getId()]->getId());
        }
    }

    public function testLoadManyWithoutObjects(): void
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-one');
        $this->repository->save($object);

        /** @var OtherDataObject[] $return */
        $return = $this->objectMapper->load([$object], 'otherDataObjects');
        $this->assertCount(0, $return);

        $loadedOtherObjects = $object->getOtherDataObjects();
        $this->assertTrue(is_array($loadedOtherObjects));
        $this->assertCount(0, $loadedOtherObjects);
    }

    public function testLoadManyToMany(): void
    {
        $object = $this->setupLoadManyToMany();

        $return = $this->objectMapper->load([$object], 'manyToManyOtherDataObjects');

        $this->assertManyToMany($object, $return);
    }

    public function testLoadManyToManyLegacy(): void
    {
        $object = $this->setupLoadManyToMany();

        $return = $this->objectMapper->loadManyToMany([$object], OtherDataObject::class, 'extended_other_rel', setter: 'setManyToManyOtherDataObjects');

        $this->assertManyToMany($object, $return);
    }

    private function setupLoadManyToMany(): ExtendedDataObject
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
            ['extendedDataObjectId' => $object->getId(), 'otherDataObjectId' => $otherObject->getId()],
            ['extendedDataObjectId' => $object->getId(), 'otherDataObjectId' => $otherObject2->getId()]
        ]);
        return $object;
    }

    private function assertManyToMany(ExtendedDataObject $object, array $return): void
    {
        $this->assertCount(2, $return);

        $loadedOtherObjects = $object->getManyToManyOtherDataObjects();
        $this->assertCount(2, $loadedOtherObjects);
        foreach ($loadedOtherObjects as $otherObject) {
            $returnedObject = $return[$otherObject->getId()] ?? null;
            $this->assertInstanceOf(OtherDataObject::class, $returnedObject);
            $this->assertEquals($otherObject->getId(), $returnedObject->getId());
            $this->assertEquals($otherObject->getName(), $returnedObject->getName());
        }
    }

    public function testLoadPolymorphic(): void
    {
        $polymorphicExtended = new ExtendedDataObject();
        $this->objectMapper->save($polymorphicExtended);
        $polymorphicOther = new OtherDataObject();
        $this->objectMapper->save($polymorphicOther);

        $withExtended = new ExtendedDataObject();
        $withExtended->setPolymorphicId($polymorphicExtended->getId());
        $withExtended->setPolymorphicClass('ExtendedDataObject');
        $withOther = new ExtendedDataObject();
        $withOther->setPolymorphicId($polymorphicOther->getId());
        $withOther->setPolymorphicClass('OtherDataObject');
        $withNull = new ExtendedDataObject();

        $objects = [$withExtended, $withOther, $withNull];
        $this->objectMapper->saveAll($objects);
        $this->objectMapper->load($objects, 'polymorphic');

        $this->assertPolymorphic($objects);
    }

    public function testSaveOne(): void
    {
        $objects = $this->setupSaveOne();

        $this->repository->saveAll($objects);
        $relationshipSaver = $this->objectMapper->getRelationshipManager();
        $relationshipSaver->save($objects, 'otherDataObject');

        $this->assertSaveOne($objects);
    }

    public function testSaveOneLegacy(): void
    {
        $objects = $this->setupSaveOne();

        $this->repository->saveAll($objects);
        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveOne($objects, OtherDataObject::class);

        $this->assertSaveOne($objects);
    }

    /**
     * @return ExtendedDataObject[]
     */
    private function setupSaveOne(): array
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
        return $objects;
    }

    /**
     * @param ExtendedDataObject[] $objects
     */
    private function assertSaveOne(array $objects): void
    {
        foreach ($objects as $i => $object) {
            $otherObject = $object->getOtherDataObject();
            if ($i < 2) {
                $this->assertGreaterThan(0, $otherObject->getId());
                $this->assertEquals($otherObject->getId(), $object->getOtherDataObjectId());
                $this->assertEquals($object->getId(), $otherObject->getExtendedDataObjectId());
                $fromDb = $this->repository->find($object->getId(), false);
                $this->assertEquals($otherObject->getId(), $fromDb->getOtherDataObjectId());
            } else { //object 3 has no otherDataObject
                $this->assertNull($object->getOtherDataObjectId());
            }
        }
    }

    public function testSaveOneNoForeignObjects(): void
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save one-to-one 1');

        $this->repository->saveAll($objects);
        $relationshipSaver = $this->objectMapper->getRelationshipManager();
        $relationshipSaver->save($objects, 'otherDataObject');

        $fromDb = $this->repository->find($object->getId(), false);
        $this->assertInstanceOf(ExtendedDataObject::class, $fromDb);
    }

    public function testSaveMany(): void
    {
        $objects = $this->setupSaveMany();
        $otherObjectsToDelete = $this->setupSaveManyDeleted($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipManager();
        $relationshipSaver->save($objects, 'otherDataObjects');

        $this->assertSaveMany($objects, $otherObjectsToDelete);
    }

    public function testSaveManyLegacy(): void
    {
        $objects = $this->setupSaveMany();
        $otherObjectsToDelete = $this->setupSaveManyDeleted($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveMany($objects, OtherDataObject::class);

        $this->assertSaveMany($objects, $otherObjectsToDelete);
    }

    /**
     * @return ExtendedDataObject[]
     */
    private function setupSaveMany(): array
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many 1-1');
        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object one-to-many 1-2');

        $otherObject3 = new OtherDataObject();
        $otherObject3->setName('Other object one-to-many 2-1');
        $otherObject4 = new OtherDataObject();
        $otherObject4->setName('Other object one-to-many 2-2');

        $objects = [];
        $object = new ExtendedDataObject();
        $object2 = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save one-to-many 1')->setOtherDataObjects([$otherObject, $otherObject2]);
        $objects[] = $object2->setMyColumn('Save one-to-many 2')->setOtherDataObjects([$otherObject3, $otherObject4]);

        $this->repository->saveAll($objects);
        return $objects;
    }

    /**
     * @param ExtendedDataObject[] $objects
     * @return OtherDataObject[]
     */
    private function setupSaveManyDeleted(array $objects): array
    {
        $otherObjectToDelete = new OtherDataObject();
        $otherObjectToDelete->setName('Other object one-to-many 1-3');
        $otherObjectToDelete2 = new OtherDataObject();
        $otherObjectToDelete2->setName('Other object one-to-many 2-3');

        $otherObjectToDelete->setExtendedDataObjectId($objects[0]->getId());
        $otherObjectToDelete2->setExtendedDataObjectId($objects[1]->getId());
        $otherDataObjects = [$otherObjectToDelete, $otherObjectToDelete2];
        $this->objectMapper->saveAll($otherDataObjects);
        return $otherDataObjects;
    }

    /**
     * @param ExtendedDataObject[] $objects
     * @param OtherDataObject[] $otherObjectsToDelete
     */
    private function assertSaveMany(array $objects, array $otherObjectsToDelete): void
    {
        foreach ($objects as $object) {
            foreach ($object->getOtherDataObjects() as $otherObject) {
                $this->assertGreaterThan(0, $otherObject->getId());
                $this->assertEquals($object->getId(), $otherObject->getExtendedDataObjectId());
            }
        }

        foreach ($otherObjectsToDelete as $otherObjectToDelete) {
            $fromDb = $this->objectMapper->findOneBy(OtherDataObject::class, ['id' => $otherObjectToDelete->getId(), 'isDeleted' => 1]);
            $this->assertTrue($fromDb->isDeleted());
        }
    }

    public function testSaveManyMove(): void
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

        $relationshipSaver = $this->objectMapper->getRelationshipManager();
        $relationshipSaver->save($objects, 'otherDataObjects');

        $this->assertEquals($object->getId(), $otherObjectToMove->getExtendedDataObjectId());
        $others1 = $object->getOtherDataObjects();
        unset($others1[2]);
        $object->setOtherDataObjects($others1);
        $others2 = $object2->getOtherDataObjects();
        $others2[] = $otherObjectToMove;
        $object2->setOtherDataObjects($others2);

        $relationshipSaver->save($objects, 'otherDataObjects');

        $otherObjectToMove = $this->objectMapper->find(OtherDataObject::class, $otherObjectToMove->getId(), false);
        $this->assertEquals($object2->getId(), $otherObjectToMove->getExtendedDataObjectId());
        $this->assertFalse($otherObjectToMove->isDeleted());

        $others1[] = $otherObjectToMove;
        $object->setOtherDataObjects($others1);
        unset($others2[2]);
        $object2->setOtherDataObjects($others2);

        $relationshipSaver->save($objects, 'otherDataObjects');

        $otherObjectToMove = $this->objectMapper->find(OtherDataObject::class, $otherObjectToMove->getId(), false);
        $this->assertEquals($object->getId(), $otherObjectToMove->getExtendedDataObjectId());
        $this->assertFalse($otherObjectToMove->isDeleted());
    }

    public function testSaveManyInsertion(): void
    {
        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many 1-1');
        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object one-to-many 1-2');

        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save one-to-many 1')->setOtherDataObjects([$otherObject, $otherObject2]);

        $this->repository->saveAll($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipManager();
        $relationshipSaver->save($objects, 'otherDataObjects');

        $this->assertGreaterThan(0, $otherObject->getId());
        $this->assertGreaterThan(0, $otherObject2->getId());
        $this->assertEquals($object->getId(), $otherObject->getExtendedDataObjectId());
        $this->assertEquals($object->getId(), $otherObject2->getExtendedDataObjectId());

        $newOtherObject = new OtherDataObject();
        $newOtherObject->setName('New other Object');
        $otherObjects = $object->getOtherDataObjects();
        array_splice($otherObjects, 1, 0, [$newOtherObject]);
        $object->setOtherDataObjects($otherObjects);
        $relationshipSaver->save([$object], 'otherDataObjects');
        $this->assertGreaterThan(0, $newOtherObject->getId());
    }

    public function testSaveManyToManyLinks(): void
    {
        $objects = $this->setupManyToMany(true);
        $otherObjectsToDelete = $this->setupManyToManyToDelete($objects);

        $this->repository->saveAll($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipManager();
        $relationshipSaver->save($objects, 'shallowOtherDataObjects');

        $this->assertManyToManyLinks($objects, $otherObjectsToDelete, true);
    }

    public function testSaveManyToManyLinksLegacy(): void
    {
        $objects = $this->setupManyToMany(true);
        $otherObjectsToDelete = $this->setupManyToManyToDelete($objects);

        $this->repository->saveAll($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveManyToManyLinks($objects, OtherDataObject::class, 'extended_other_rel', 'getShallowOtherDataObjects');

        $this->assertManyToManyLinks($objects, $otherObjectsToDelete, true);
    }


    /**
     * @return ExtendedDataObject[]
     */
    private function setupManyToMany(bool $shallow): array
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

        $this->objectMapper->saveAll($otherObjects);

        $objects = [];
        $object = new ExtendedDataObject();
        $object2 = new ExtendedDataObject();
        $objects[] = $object->setMyColumn('Save many-to-many 1');
        $objects[] = $object2->setMyColumn('Save many-to-many 2');
        $this->objectMapper->saveAll($objects);

        if ($shallow) {
            $this->objectMapper->saveAll($objects);
            $object->setShallowOtherDataObjects([$otherObject, $otherObject2]);
            $object2->setShallowOtherDataObjects([$otherObject3, $otherObject4]);
        } else {
            $object->setManyToManyOtherDataObjects([$otherObject, $otherObject2]);
            $object2->setManyToManyOtherDataObjects([$otherObject3, $otherObject4]);
        }

        return $objects;
    }

    /**
     * @param ExtendedDataObject[] $objects
     * @return OtherDataObject[]
     */
    private function setupManyToManyToDelete(array $objects): array
    {
        $otherObjectToDelete = new OtherDataObject();
        $otherObjects[] = $otherObjectToDelete->setName('Other object many-to-many 1-3');
        $otherObjectToDelete2 = new OtherDataObject();
        $otherObjects[] = $otherObjectToDelete2->setName('Other object many-to-many 2-3');
        $this->objectMapper->saveAll($otherObjects);

        $queryHelper = $this->objectMapper->getQueryHelper();
        $queryHelper->massInsert('extended_other_rel', [
            ['extendedDataObjectId'=>$objects[0]->getId(), 'otherDataObjectId'=> $otherObjectToDelete->getId()],
            ['extendedDataObjectId'=>$objects[1]->getId(), 'otherDataObjectId'=> $otherObjectToDelete2->getId()],
        ]);
        return $otherObjects;
    }

    /**
     * @param ExtendedDataObject[] $objects
     * @param OtherDataObject[] $otherObjectsToDelete
     */
    private function assertManyToManyLinks(array $objects, array $otherObjectsToDelete, bool $shallow): void
    {
        $queryHelper = $this->objectMapper->getQueryHelper();
        foreach ($objects as $object) {
            $objectLinks = $queryHelper->buildSelectQuery('extended_other_rel', self::$connection->quoteIdentifier('otherDataObjectId'), ['extendedDataObjectId' => $object->getId()])
                ->executeQuery()->fetchFirstColumn();

            $this->assertCount(2, $objectLinks);
            if ($shallow) {
                foreach ($object->getShallowOtherDataObjects() as $otherObject) {
                    $this->assertTrue(in_array($otherObject->getId(), $objectLinks));
                }
            } else {
                foreach ($object->getManyToManyOtherDataObjects() as $otherObject) {
                    $this->assertTrue(in_array($otherObject->getId(), $objectLinks));
                }
            }

            foreach ($otherObjectsToDelete as $deleted) {
                $this->assertFalse(in_array($deleted->getId(), $objectLinks));
            }
        }
    }

    public function testSaveManyToMany(): void
    {
        $objects = $this->setupManyToMany(false);
        $otherObjectsToDelete = $this->setupManyToManyToDelete($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipManager();
        $relationshipSaver->save($objects, 'manyToManyOtherDataObjects');

        $this->assertManyToManyLinks($objects, $otherObjectsToDelete, false);
    }

    public function testSaveManyToManyLegacy(): void
    {
        $objects = $this->setupManyToMany(false);
        $otherObjectsToDelete = $this->setupManyToManyToDelete($objects);

        $relationshipSaver = $this->objectMapper->getRelationshipSaver();
        $relationshipSaver->saveManyToMany($objects, OtherDataObject::class, 'extended_other_rel', 'getManyToManyOtherDataObjects');

        $this->assertManyToManyLinks($objects, $otherObjectsToDelete, false);
    }

    public function testSavePolymorphic(): void
    {
        $withExtended = new ExtendedDataObject();
        $withExtended->setPolymorphic(new ExtendedDataObject());
        $withOther = new ExtendedDataObject();
        $withOther->setPolymorphic(new OtherDataObject());
        $withNull = new ExtendedDataObject();

        $objects = [$withExtended, $withOther, $withNull];
        $this->objectMapper->saveAll($objects);
        $this->objectMapper->getRelationshipManager()->save($objects, 'polymorphic');

        $this->assertPolymorphic($objects);
    }

    public function testLoadAndSaveAllRelationships(): void
    {
        $object = new ExtendedDataObject();
        $this->objectMapper->save($object);
        $object->setOtherDataObject(new OtherDataObject());
        $object->setOtherDataObjects([new OtherDataObject(), new OtherDataObject()]);
        $manyToManyObjects = [new OtherDataObject(), new OtherDataObject(), new OtherDataObject()];
        $object->setManyToManyOtherDataObjects($manyToManyObjects);
        $object->setShallowOtherDataObjects($manyToManyObjects);
        $this->objectMapper->getRelationshipManager()->saveAll([$object]);
        $this->assertGreaterThan(0, $object->getOtherDataObject()->getId());
        foreach ($object->getOtherDataObjects() as $otherDataObject) {
            $this->assertGreaterThan(0, $otherDataObject->getId());
            $this->assertEquals($object->getId(), $otherDataObject->getExtendedDataObjectId());
        }
        foreach ($object->getManyToManyOtherDataObjects() as $otherDataObject) {
            $this->assertGreaterThan(0, $otherDataObject->getId());
        }

        /** @var ExtendedDataObject $fromDb */
        $fromDb = $this->objectMapper->find(ExtendedDataObject::class, $object->getId(), false);
        $this->objectMapper->getRelationshipManager()->loadAll([$fromDb]);
        $this->assertNotNull($fromDb->getOtherDataObject());
        $this->assertCount(2, $fromDb->getOtherDataObjects());
        $this->assertCount(3, $fromDb->getManyToManyOtherDataObjects());
    }

    public function testFindByOneToOneJoin()
    {
        $object = new ExtendedDataObject();
        $this->objectMapper->save($object);
        $object->setOtherDataObject((new OtherDataObject())->setName('Bob'));
        $this->objectMapper->getRelationshipManager()->save([$object], 'otherDataObject');

        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $fromDb = $repo->findByOtherColumn('Bob');
        $this->assertNotEmpty($fromDb);
    }

    public function testFindByOneToManyJoin()
    {
        $object = new ExtendedDataObject();
        $this->objectMapper->save($object);
        $object->setOtherDataObjects([(new OtherDataObject())->setName('Bob')]);
        $this->objectMapper->getRelationshipManager()->save([$object], 'otherDataObjects');

        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $fromDb = $repo->findByOtherColumnOneToMany('Bob');
        $this->assertNotEmpty($fromDb);
    }

    public function testFindByManyToManyJoin()
    {
        $object = new ExtendedDataObject();
        $this->objectMapper->save($object);
        $object->setManyToManyOtherDataObjects([(new OtherDataObject())->setName('Bob')]);
        $this->objectMapper->getRelationshipManager()->save([$object], 'manyToManyOtherDataObjects');

        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $fromDb = $repo->findByOtherColumnManyToMany('Bob');
        $this->assertNotEmpty($fromDb);
    }

    public function testOffsetPagedQuery(): void
    {
        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $pager = $repo->findAllPaged();
        $this->assertInstanceOf(OffsetPagedQuery::class, $pager);

        $this->assertGreaterThan(1, $pager->getPages());

        for ($i = 1; $i <= $pager->getPages(); $i++) {
            $objects = $pager->getResults($i);
            $this->assertLessThanOrEqual(5, count($objects));
            $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        }
    }

    public function testSeekPagedQuery(): void
    {
        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $pager = $repo->findAllSeekPaged();
        $this->assertInstanceOf(SeekPagedQuery::class, $pager);

        $pages = $pager->getPages();
        $this->assertGreaterThan(1, $pages);

        $i = 0;
        $ids = [];
        $keys = [];
        /** @var ExtendedDataObject[] $objects */
        foreach ($pager as $key => $objects) {
            $keys[] = $key;
            $this->assertNotEmpty($objects);
            $this->assertLessThanOrEqual(5, count($objects));
            $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
            foreach($objects as $object) {
                $this->assertArrayNotHasKey($object->getId(), $ids, 'Overlapping objects between pages!');
                $ids[$object->getId()] = true;
            }
            $i++;
        }
        $this->assertEquals($pages, $i);
        $this->assertEquals($pager->getResultCount(), count($ids));

        // Test as if the key was being send from request
        $pager = $repo->findAllSeekPaged();
        $results = $pager->getResults($keys[$i-2]);
        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(5, count($objects));
    }

    /**
     * @param ExtendedDataObject[] $objects
     */
    private function assertPolymorphic(array $objects): void
    {
        foreach ($objects as $i => $object) {
            $polymorphic = $object->getPolymorphic();
            if ($i < 2) {
                $this->assertNotNull($polymorphic->getId());
                $this->assertEquals($polymorphic->getId(), $object->getPolymorphicId());
                $this->assertStringContainsString($object->getPolymorphicClass(), $polymorphic::class);
            } else {
                $this->assertNull($object->getPolymorphicId());
                $this->assertNull($object->getPolymorphicClass());
            }
        }
    }
}
