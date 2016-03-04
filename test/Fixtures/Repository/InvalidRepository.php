<?php
namespace Corma\Test\Fixtures\Repository;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvalidRepository implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
    }
}