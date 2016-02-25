<?php
namespace Corma;

use Corma\DataObject\DataObjectInterface;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\Util\QueryHelper;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Main entry point for the ORM
 */
class ObjectMapper
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
     * Creates a ObjectMapper instance using the default ObjectRepositoryFactory
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
     * ObjectMapper constructor.
     * @param QueryHelper $queryHelper
     * @param ObjectRepositoryFactoryInterface $repositoryFactory
     */
    public function __construct(QueryHelper $queryHelper, ObjectRepositoryFactoryInterface $repositoryFactory)
    {
        $this->queryHelper = $queryHelper;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param string $objectName Object class with or without namespace
     * @return Repository\ObjectRepositoryInterface
     */
    public function getRepository($objectName)
    {
        return $this->repositoryFactory->getRepository($objectName);
    }

    /**
     * Creates a new instance of the requested object
     *
     * @param string $objectName Object class with or without namespace
     * @return DataObjectInterface
     */
    public function createObject($objectName)
    {
        return $this->repositoryFactory->getRepository($objectName)->create();
    }

    /**
     * Find an object by id
     *
     * @param string $objectName Object class with or without namespace
     * @param string|int $id
     * @return DataObjectInterface
     */
    public function find($objectName, $id)
    {
        return $this->getRepository($objectName)->find($id);
    }

    /**
     * Find all of the specified object type
     *
     * @param string $objectName Object class with or without namespace
     * @return DataObjectInterface[]
     */
    public function findAll($objectName)
    {
        return $this->getRepository($objectName)->findAll();
    }

    /**
     * @param string $objectName Object class with or without namespace
     * @param array $criteria column => value pairs
     * @param array $orderBy column => order pairs
     * @param int $limit Maximum results to return
     * @param int $offset First result to return
     * @return DataObjectInterface[]
     */
    public function findBy($objectName, array $criteria, array $orderBy = [], $limit = null, $offset = null)
    {
        return $this->getRepository($objectName)->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param string $objectName Object class with or without namespace
     * @param array $criteria column => value pairs
     * @return DataObjectInterface
     */
    public function findOneBy($objectName, array $criteria)
    {
        return $this->getRepository($objectName)->findOneBy($criteria);
    }

    /**
     * @param DataObjectInterface $object
     * @return DataObjectInterface
     */
    public function save(DataObjectInterface $object)
    {
        return $this->getRepository($object->getClassName())->save($object);
    }

    /**
     * @param DataObjectInterface $object
     * @return void
     */
    public function delete(DataObjectInterface $object)
    {
        $this->getRepository($object->getClassName())->delete($object);
    }

    /**
     * @return QueryHelper
     */
    public function getQueryHelper()
    {
        return $this->queryHelper;
    }
}