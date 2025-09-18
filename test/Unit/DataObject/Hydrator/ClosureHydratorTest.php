<?php
namespace Corma\Test\Unit\DataObject\Hydrator;

use Corma\DataObject\Hydrator\PropertyHydrator\BackedEnumHydrator;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\DataObject\Hydrator\PropertyHydrator\DateTimeHydrator;
use Corma\Test\Fixtures\DateTimeObject;
use Corma\Test\Fixtures\EnumObject;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\TestEnum;
use PHPUnit\Framework\TestCase;

class ClosureHydratorTest extends TestCase
{
    public function testHydrate(): void
    {
        $hydrator = new ClosureHydrator();
        $object = new ExtendedDataObject();
        $otherObject = new OtherDataObject();
        $hydrator->hydrate($object, ['myColumn'=>4, 'otherDataObject'=>$otherObject]);
        $this->assertEquals(4, $object->getMyColumn());
        $this->assertEquals($otherObject, $object->getOtherDataObject());
    }

    public function testExtract(): void
    {
        $hydrator = new ClosureHydrator();
        $object = new ExtendedDataObject();
        $object->setMyColumn(4);
        $data = $hydrator->extract($object);
        $this->assertEquals(4, $data['myColumn']);
    }

    public function testSetHydrate(): void
    {
        $hydrator = new ClosureHydrator();
        $closure = function (){};
        $hydrator->setHydrate($closure);

        $object = new ExtendedDataObject();
        $hydrator->hydrate($object, ['myColumn'=>4]);
        $this->assertEmpty($object->getMyColumn());
    }

    public function testSetExtract(): void
    {
        $hydrator = new ClosureHydrator();
        $closure = fn() => [];
        $hydrator->setExtract($closure);

        $object = new ExtendedDataObject();
        $object->setMyColumn(4);
        $data = $hydrator->extract($object);
        $this->assertEmpty($data);
    }

    public function testDateTimePropertyHydrator(): void
    {
        $hydrator = new ClosureHydrator();
        $hydrator->addPropertyHydrator(new DateTimeHydrator());
        
        // Test hydration
        $dateTimeObject = new DateTimeObject();
        $dateString = '2025-09-18 11:12:00';
        $hydrator->hydrate($dateTimeObject, ['createdAt' => $dateString]);
        
        $this->assertInstanceOf(\DateTime::class, $dateTimeObject->getCreatedAt());
        $this->assertEquals($dateString, $dateTimeObject->getCreatedAt()->format('Y-m-d H:i:s'));
        
        // Test extraction
        $data = $hydrator->extract($dateTimeObject);
        $this->assertEquals($dateString, $data['createdAt']);
    }
    
    public function testBackedEnumPropertyHydrator(): void
    {
        $hydrator = new ClosureHydrator();
        $hydrator->addPropertyHydrator(new BackedEnumHydrator());
        
        // Test hydration
        $enumObject = new EnumObject();
        $statusValue = 'active';
        $hydrator->hydrate($enumObject, ['status' => $statusValue]);
        
        $this->assertInstanceOf(TestEnum::class, $enumObject->getStatus());
        $this->assertEquals(TestEnum::ACTIVE, $enumObject->getStatus());
        $this->assertEquals($statusValue, $enumObject->getStatus()->value);
        
        // Test extraction
        $data = $hydrator->extract($enumObject);
        $this->assertEquals($statusValue, $data['status']);
    }
}
