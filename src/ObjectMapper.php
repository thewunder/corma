<?php
namespace Corma;

use Corma\DataObject\DataObjectInterface;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\QueryHelper\QueryHelper;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Util\RelationshipLoader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
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
     * @var RelationshipLoader
     */
    private $relationshipLoader;

    /**
     * Creates a ObjectMapper instance using the default QueryHelper and ObjectRepositoryFactory
     *
     * @param Connection $db Database connection
     * @param array $namespaces Namespaces to search for data objects and repositories
     * @param CacheProvider $cache Cache for table metadata and repositories
     * @param EventDispatcherInterface $dispatcher
     * @param array $additionalDependencies Additional dependencies to inject into Repository constructors
     * @return static
     */
    public static function create(Connection $db, array $namespaces, CacheProvider $cache = null, EventDispatcherInterface $dispatcher = null, array $additionalDependencies = [])
    {
        if($cache === null) {
            $cache = new ArrayCache();
        }

        $queryHelper = self::createQueryHelper($db, $cache);
        $repositoryFactory = new ObjectRepositoryFactory($namespaces);
        $instance = new static($queryHelper, $repositoryFactory);
        $dependencies = array_merge([$db, $instance, $cache, $dispatcher], $additionalDependencies);
        $repositoryFactory->setDependencies($dependencies);
        return $instance;
    }

    /**
     * @param Connection $db
     * @param CacheProvider $cache
     * @return QueryHelperInterface
     */
    protected static function createQueryHelper(Connection $db, CacheProvider $cache)
    {
        $database = $db->getDatabasePlatform()->getReservedKeywordsList()->getName();
        $database = preg_replace('/[^A-Za-z]/', '', $database); //strip version
        $className = "Corma\\QueryHelper\\{$database}QueryHelper";
        if(class_exists($className)) {
            return new $className($db, $cache);
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
     * @param bool $useCache Use cache?
     * @return DataObjectInterface
     */
    public function find($objectName, $id, $useCache = true)
    {
        return $this->getRepository($objectName)->find($id, $useCache);
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
     * 
     * @see QueryHelperInterface::processWhereQuery() For details on $criteria
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
     * 
     * @see QueryHelperInterface::processWhereQuery() For details on $criteria
     */
    public function findOneBy($objectName, array $criteria)
    {
        return $this->getRepository($objectName)->findOneBy($criteria);
    }

    /**
     * Loads a foreign relationship where a property on the supplied objects references an id for another object.
     * Can be used to load a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * This works on objects of mixed type, although they must have exactly the same $foreignIdColumn, or use the default.
     *
     * $foreignIdColumn defaults to foreignObjectId if the $className is Namespace\\ForeignObject
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign object to load
     * @param string $foreignIdColumn Column / property on this object that relates to the foreign table's id (defaults to if the class = ForeignObject foreignObjectId)
     * @return DataObjectInterface[] Loaded objects keyed by id
     */
    public function loadOne(array $objects, $className, $foreignIdColumn = null)
    {
        $objectsByClass = $this->groupByClass($objects);

        $loadedObjects = [];
        foreach($objectsByClass as $class => $classObjects) {
            $loadedObjects += $this->getRepository($class)->loadOne($classObjects, $className, $foreignIdColumn);
        }
        return $loadedObjects;
    }

    /**
     * Loads a foreign relationship where a column on the foreign object references the id for the supplied objects.
     * Used to load the "many" side of a one-to-many relationship.
     *
     * This works on objects of mixed type, although they must have exactly the same $foreignColumn, or use the default.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $foreignColumn Column / property on foreign object that relates to this object id
     * @return DataObjectInterface[] Loaded objects keyed by id
     */
    public function loadMany(array $objects, $className, $foreignColumn = null)
    {
        $objectsByClass = $this->groupByClass($objects);

        $loadedObjects = [];
        foreach($objectsByClass as $class => $classObjects) {
            $loadedObjects += $this->getRepository($class)->loadMany($classObjects, $className, $foreignColumn);
        }
        return $loadedObjects;
    }

    /**
     * Loads objects of the foreign class onto the supplied objects linked by a link table containing the id's of both objects.
     *
     * This works theoretically on objects of mixed type, although they must have the same link table, which makes this in reality only usable.
     * by for objects of the same class.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     * @return DataObjectInterface[] Loaded objects keyed by id
     */
    public function loadManyToMany(array $objects, $className, $linkTable, $idColumn = null, $foreignIdColumn = null)
    {
        $objectsByClass = $this->groupByClass($objects);

        $loadedObjects = [];
        foreach($objectsByClass as $class => $classObjects) {
            $loadedObjects += $this->getRepository($class)->loadManyToMany($classObjects, $className, $linkTable, $idColumn, $foreignIdColumn);
        }
        return $loadedObjects;
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
     * Persists all objects to the database.
     *
     * This method works on objects of mixed type.
     *
     * @param DataObjectInterface[] $objects
     */
    public function saveAll(array $objects)
    {
        $objectsByClass = $this->groupByClass($objects);

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
     * Delete all objects from the database by their ids.
     *
     * This method works on objects of mixed type.
     *
     * @param DataObjectInterface[] $objects
     */
    public function deleteAll(array $objects)
    {
        $objectsByClass = $this->groupByClass($objects);

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

    /**
     * @return RelationshipLoader
     */
    public function getRelationshipLoader()
    {
        if($this->relationshipLoader) {
            return $this->relationshipLoader;
        }
        return $this->relationshipLoader = new RelationshipLoader($this);
    }

    /**
     * @param DataObjectInterface[] $objects
     * @return array
     */
    protected function groupByClass(array $objects)
    {
        $objectsByClass = [];
        foreach ($objects as $object) {
            $objectsByClass[$object->getClassName()][] = $object;
        }
        return $objectsByClass;
    }
}
