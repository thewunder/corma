<?php
namespace Corma\Test\DataObject\Factory;

use Corma\DataObject\Factory\PdoObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\Test\Fixtures\ObjectWithDependencies;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PdoObjectFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testDependencies()
    {
        $factory = new PdoObjectFactory(new ClosureHydrator());
        $object = $factory->create(ObjectWithDependencies::class, [new EventDispatcher()]);
        $this->assertInstanceOf(ObjectWithDependencies::class, $object);
    }
}