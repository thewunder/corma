<?php

namespace Corma\Test\Integration\Relationship;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Integration\BaseIntegrationCase;

final class OneToManyTest extends BaseIntegrationCase
{
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
}
