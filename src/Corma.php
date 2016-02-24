<?php
namespace Corma;

use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\Util\QueryHelper;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Main entry point for the ORM
 */
class Corma
{
    /**
    /**
     * @var ObjectRepositoryFactoryInterface
     */
    private $repositoryFactory;

    /**
     * @var QueryHelper
     */
    private $queryHelper;

    /**
     * Creates a Corma instance using the default ObjectRepositoryFactory
     *
     * @param Connection $db Database connection
     * @param EventDispatcherInterface $dispatcher
     * @param Cache $cache Cache for table metadata
     * @param array $namespaces Object Namespaces to search for Repositories.  Repositories
     * @param array $additionalDependencies Additional dependencies to inject into Repository constructors
     * @return static
     */
    public static function create(Connection $db, EventDispatcherInterface $dispatcher, Cache $cache, array $namespaces, array $additionalDependencies = [])
    {
        $queryHelper = new QueryHelper($db, $cache);
        $dependencies = array_merge([$db, $dispatcher, $queryHelper], $additionalDependencies);
        return new static($queryHelper, new ObjectRepositoryFactory($namespaces, $dependencies));
    }

    /**
     * Corma constructor.
     * @param QueryHelper $queryHelper
     * @param ObjectRepositoryFactoryInterface $repositoryFactory
     */
    public function __construct(QueryHelper $queryHelper, ObjectRepositoryFactoryInterface $repositoryFactory)
    {
        $this->queryHelper = $queryHelper;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param $objectName
     * @return Repository\ObjectRepositoryInterface
     */
    public function getRepository($objectName)
    {
        return $this->repositoryFactory->getRepository($objectName);
    }

    /**
     * @return QueryHelper
     */
    public function getQueryHelper()
    {
        return $this->queryHelper;
    }
}