<?php
namespace Corma\Test\DataObject\Hydrator;

use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
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
}
