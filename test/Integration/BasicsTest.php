<?php

namespace Corma\Test\Integration;

use Corma\DBAL\Exception;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use PHPUnit\Framework\Attributes\Depends;

final class BasicsTest extends BaseIntegrationCase
{
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

        /** @var ExtendedDataObject[] $allFromDb */
        $allFromDb = $this->repository->findByIds($this->identifier->getIds($objects), false);
        $this->assertCount(2, $allFromDb);
        foreach ($allFromDb as $fromDb) {
            $this->assertTrue($fromDb->isDeleted());
        }
    }

    public function testUpsertWithoutPrimaryKey(): void
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Upsert EDO');
        $this->objectMapper->save($object);

        $otherObject = new OtherDataObject();
        $otherObject->setName('Upsert ODO');
        $this->objectMapper->save($otherObject);

        $return = $this->objectMapper->getQueryHelper()
            ->massUpsert('extended_other_rel', [['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=>$otherObject->getId()]]);

        $this->assertEquals(1, $return);
    }

    public function testIsDuplicateException(): void
    {
        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (Exception $e) {
            $this->assertTrue($this->objectMapper->getQueryHelper()->isDuplicateException($e));
            return;
        }

        $this->markTestIncomplete('Expected Exception was not thrown');
    }
}
