<?php
namespace Corma;

use Corma\DataObject\DataObjectInterface;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\QueryHelper\QueryHelper;
use Corma\QueryHelper\QueryHelperInterface;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Main entry point for the ORM
 */
class ObjectMapper
{
    /**
     * @var ObjectRepositoryFactoryInterface
     */
    private $repositoryFactory;

    /**
     * @var QueryHelperInterface
     */
    private $queryHelper;

    /**
     * Creates a ObjectMapper instance using the default QueryHelper and ObjectRepositoryFactory
     *
     * @param Connection $db Database connection
     * @param EventDispatcherInterface $dispatcher
     * @param array $namespaces Namespaces to search for data objects and repositories
     * @param Cache $cache Cache for table metadata and repositories
     * @param array $additionalDependencies Additional dependencies to inject into Repository constructors
     * @return static
     */
    public static function create(Connection $db, EventDispatcherInterface $dispatcher, array $namespaces, Cache $cache = null, array $additionalDependencies = [])
    {
        if($cache === null) {
            $cache = new ArrayCache();
        }

        $queryHelper = self::createQueryHelper($db, $cache);
        $repositoryFactory = new ObjectRepositoryFactory($namespaces);
        $instance = new static($queryHelper, $repositoryFactory);
        $dependencies = array_merge([$db, $dispatcher, $instance, $cache], $additionalDependencies);
        $repositoryFactory->setDependencies($dependencies);
        return $instance;
    }

    /**
     * @param Connection $db
     * @param Cache $cache
     * @return QueryHelperInterface
     */
    protected static function createQueryHelper(Connection $db, Cache $cache)
    {
        $database = $db->getDatabasePlatform()->getReservedKeywordsList()->getName();
        $database = preg_replace('/[^A-Za-z]/', '', $database); //strip version
        $className = "Corma\\QueryHelper\\{$database}QueryHelper";
        if(class_exists($className)) {
            new $className($db, $cache);
        }

        return new QueryHelper($db, $cache);
    }

    /**
     * ObjectMapper constructor.
     * @param QueryHelperInterface $queryHelper
     * @param ObjectRepositoryFactoryInterface $repositoryFactory
     */
    public function __construct(QueryHelperInterface $queryHelper, ObjectRepositoryFactoryInterface $repositoryFactory)
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
     * Find objects by ids
     *
     * @param string $objectName Object class with or without namespace
     * @param array $ids
     * @return DataObjectInterface[]
     */
    public function findByIds($objectName, array $ids)
    {
        return $this->getRepository($objectName)->findByIds($ids);
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
     * Finds a single object by any criteria
     *
     * @param string $objectName Object class with or without namespace
     * @param array $criteria column => value pairs
     * @return DataObjectInterface
     */
    public function findOneBy($objectName, array $criteria)
    {
        return $this->getRepository($objectName)->findOneBy($criteria);
    }

    /**
     * Persists a single object to the database
     *
     * @param DataObjectInterface $object
     * @return DataObjectInterface
     */
    public function save(DataObjectInterface $object)
    {
        return $this->getRepository($object->getClassName())->save($object);
    }

    /**
     * Persists all objects to the database
     *
     * @param DataObjectInterface[] $objects
     */
    public function saveAll(array $objects)
    {
        $objectsByClass = [];
        foreach($objects as $object) {
            $objectsByClass[$object->getClassName()][] = $object;
        }

        foreach($objectsByClass as $class => $classObjects) {
            $this->getRepository($class)->saveAll($classObjects);
        }
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
     * Delete all objects from the database by their ids
     *
     * @param DataObjectInterface[] $objects
     */
    public function deleteAll(array $objects)
    {
        $objectsByClass = [];
        foreach($objects as $object) {
            $objectsByClass[$object->getClassName()][] = $object;
        }

        foreach($objectsByClass as $class => $classObjects) {
            $this->getRepository($class)->deleteAll($classObjects);
        }
    }

    /**
     * @return QueryHelperInterface
     */
    public function getQueryHelper()
    {
        return $this->queryHelper;
    }
}
