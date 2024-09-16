<?php

namespace Corma\Test\Integration;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;

final class RelationshipSaveTest extends BaseIntegrationCase
{
    public function testSaveAllWithRelationshipNames()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Save All');

        $objects = [$object];
        $object2 = new ExtendedDataObject();
        $otherDataObject = (new OtherDataObject())->setName('Saved with 2');
        $object2->setOtherDataObject($otherDataObject);
        $objects[] = $object2->setMyColumn('Save All 2');

        $inserts = $this->repository->saveAll($objects, 'otherDataObject');

        $this->assertEquals(2, $inserts);
        $this->assertGreaterThan(0, $otherDataObject->getId());
    }

    public function testSaveAllWithRelationshipStar()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Save All');
        $otherDataObject = (new OtherDataObject())->setName('Saved with 1, polymorphic');
        $object->setPolymorphic($otherDataObject);

        $objects = [$object];
        $object2 = new ExtendedDataObject();
        $otherDataObject2 = (new OtherDataObject())->setName('Saved with 2');
        $object2->setOtherDataObject($otherDataObject2);
        $objects[] = $object2->setMyColumn('Save All 2');

        $inserts = $this->repository->saveAll($objects, '*');

        $this->assertEquals(2, $inserts);
        $this->assertGreaterThan(0, $otherDataObject->getId());
        $this->assertGreaterThan(0, $otherDataObject2->getId());
    }

    public function testSaveAllWithRelationshipClosure()
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Save All');
        $this->repository->save($object);
        $object->setMyColumn('Save All Updated');

        $objects = [$object];
        $object2 = new ExtendedDataObject();
        $objects[] = $object2->setMyColumn('Save All 2');

        $called = false;
        $closure = function () use(&$called) {$called = true;};

        $inserts = $this->repository->saveAll($objects, $closure);

        $this->assertEquals(2, $inserts);
        $this->assertTrue($called);
    }

    public function testSaveWithRelationshipNames()
    {
        $object = new ExtendedDataObject();

        $otherObject = new OtherDataObject();
        $otherObject->setName('Other object one-to-many');
        $otherObject2 = new OtherDataObject();
        $otherObject2->setName('Other object one-to-one');
        $object->setOtherDataObjects([$otherObject])->setOtherDataObject($otherObject2);
        $this->objectMapper->save($object, 'otherDataObjects');

        // only the otherDataObjects should be saved
        $this->assertGreaterThan(0, $object->getId());
        $this->assertGreaterThan(0, $otherObject->getId());
        $this->assertNull($otherObject2->getId());
    }

    public function testSaveWithRelationshipClosure()
    {
        $object = new ExtendedDataObject();
        $itRan = false;
        $closure = function() use (&$itRan) {$itRan = true;};
        $this->objectMapper->save($object, $closure);

        $this->assertGreaterThan(0, $object->getId());
        $this->assertTrue($itRan);
    }

    public function testSaveWithRelationshipNull()
    {
        $object = new ExtendedDataObject();
        $this->objectMapper->save($object, null);

        $this->assertGreaterThan(0, $object->getId());
    }
}
