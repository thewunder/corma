<?php

namespace Corma\Test\Integration\Relationship;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Integration\BaseIntegrationCase;

final class OneToOneTest extends BaseIntegrationCase
{
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
}
