<?php
namespace Corma\Test\DataObject\Hydrator;

use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;

class ClosureHydratorTest extends \PHPUnit_Framework_TestCase
{
    public function testHydrate()
    {
        $hydrator = new ClosureHydrator();
        $object = new ExtendedDataObject();
        $otherObject = new OtherDataObject();
        $hydrator->hydrate($object, ['myColumn'=>4, 'otherDataObject'=>$otherObject]);
        $this->assertEquals(4, $object->getMyColumn());
        $this->assertEquals($otherObject, $object->getOtherDataObject());
    }

    public function testExtract()
    {
        $hydrator = new ClosureHydrator();
        $object = new ExtendedDataObject();
        $object->setMyColumn(4);
        $data = $hydrator->extract($object);
        $this->assertEquals(4, $data['myColumn']);
    }

    public function testSetHydrate()
    {
        $hydrator = new ClosureHydrator();
        $closure = function (){};
        $hydrator->setHydrateClosure($closure);

        $object = new ExtendedDataObject();
        $hydrator->hydrate($object, ['myColumn'=>4]);
        $this->assertEmpty($object->getMyColumn());
    }

    public function testSetExtract()
    {
        $hydrator = new ClosureHydrator();
        $closure = function (){return [];};
        $hydrator->setExtractClosure($closure);

        $object = new ExtendedDataObject();
        $object->setMyColumn(4);
        $data = $hydrator->extract($object);
        $this->assertEmpty($data);
    }
}
