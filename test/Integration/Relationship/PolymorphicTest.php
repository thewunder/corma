<?php

namespace Corma\Test\Integration\Relationship;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Integration\BaseIntegrationCase;

final class PolymorphicTest extends BaseIntegrationCase
{
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

    public function testFindByPolymorphicJoin()
    {
        $object = new ExtendedDataObject();
        $object->setPolymorphic((new OtherDataObject())->setName('Poly'));
        $object2 = new ExtendedDataObject();
        $object2->setPolymorphic((new ExtendedDataObject())->setMyColumn('Morphic'));
        $this->objectMapper->getRelationshipManager()->save([$object, $object2], 'polymorphic');
        $this->objectMapper->saveAll([$object, $object2]);

        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $fromDb = $repo->findByOtherColumnPolymorphic('Poly');
        $this->assertCount(1, $fromDb);
        $this->assertEquals($object->getId(), $fromDb[0]->getId());
    }
}
