<?php

namespace Corma\Test\Integration;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;

final class RelationshipLoadTest extends BaseIntegrationCase
{
    public function testLoadStar(): void
    {
        $polymorphicObject = new ExtendedDataObject();
        $polymorphicObject->setMyColumn('Polymorphic');
        $this->objectMapper->save($polymorphicObject);

        $object = new ExtendedDataObject();
        $object->setMyColumn('many-to-one');
        $object->setPolymorphicClass('ExtendedDataObject');
        $object->setPolymorphicId($polymorphicObject->getId());
        $this->repository->save($object);

        $otherObjects = [];
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-one 1')->setExtendedDataObjectId($object->getId());
        $otherObject = new OtherDataObject();
        $otherObjects[] = $otherObject->setName('Other object many-to-one 2')->setExtendedDataObjectId($object->getId());
        $this->objectMapper->saveAll($otherObjects);

        $loadedObjects = $this->objectMapper->load([$object], '*');
        $this->assertNotNull($object->getPolymorphic());
        $this->assertEquals($polymorphicObject->getId(), $object->getPolymorphic()->getId());
        $this->assertCount(2, $object->getOtherDataObjects());
        $this->assertArrayHasKey('polymorphic', $loadedObjects);
        $this->assertCount(1, $loadedObjects['polymorphic']);
        $this->assertArrayHasKey('otherDataObjects', $loadedObjects);
        $this->assertCount(2, $loadedObjects['otherDataObjects']);
    }
}
