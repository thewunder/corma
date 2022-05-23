<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\ObjectMapper;
use Corma\Repository\ObjectRepository;
use Doctrine\DBAL\Connection;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WithDependenciesRepository extends ObjectRepository
{
    public function __construct(Connection $db, ObjectMapper $objectMapper, CacheInterface $cache, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($db, $objectMapper, $cache, $dispatcher);
        
        $this->objectDependencies = [$objectMapper];
    }
}
