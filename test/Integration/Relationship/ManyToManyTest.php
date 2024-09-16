<?php

namespace Corma\Test\Integration\Relationship;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Integration\BaseIntegrationCase;

final class ManyToManyTest extends BaseIntegrationCase
{
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
            $objectLinks = $queryHelper->buildSelectQuery('extended_other_rel', self::$platform->getConnection()->quoteIdentifier('otherDataObjectId'), ['extendedDataObjectId' => $object->getId()])
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
}
