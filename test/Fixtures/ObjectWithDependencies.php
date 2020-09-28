<?php
namespace Corma\Test\Fixtures;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ObjectWithDependencies extends BaseDataObject
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
}
