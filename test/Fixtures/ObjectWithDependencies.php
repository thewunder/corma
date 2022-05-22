<?php
namespace Corma\Test\Fixtures;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ObjectWithDependencies extends BaseDataObject
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }
}
