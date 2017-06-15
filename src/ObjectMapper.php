<?php
namespace Corma;

use Corma\DataObject\ObjectManagerFactory;
use Corma\Relationship\RelationshipSaver;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\QueryHelper\QueryHelper;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Relationship\RelationshipLoader;
use Corma\Util\Inflector;
use Corma\Util\UnitOfWork;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\DBAL\Connection;
use Minime\Annotations\Interfaces\ReaderInterface;
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
     * @var ObjectManagerFactory
     */
    private $objectManagerFactory;

    /**
     * @var QueryHelperInterface
     */
    private $queryHelper;

    /**
     * @var RelationshipLoader
     */
    private $relationshipLoader;

    /**
     * @var RelationshipSaver
     */
    private $relationshipSaver;

    /**
     * @var Inflector
     */
    private $inflector;

    /**
     * Creates a ObjectMapper instance using the default QueryHelper and ObjectRepositoryFactory
     *
     * @param Connection $db Database connection
     * @param CacheProvider $cache Cache for table metadata and repositories
     * @param EventDispatcherInterface $dispatcher
     * @param ReaderInterface $reader
     * @param array $additionalDependencies Additional dependencies to inject into Repository constructors
     * @return static
     */
    public static function withDefaults(Connection $db, ?CacheProvider $cache = null, ?EventDispatcherInterface $dispatcher = null, ?ReaderInterface $reader = null, array $additionalDependencies = [])
    {
        if ($cache === null) {
            $cache = new ArrayCache();
        }

        $queryHelper = self::createQueryHelper($db, $cache);
        $repositoryFactory = new ObjectRepositoryFactory();
        $inflector = new Inflector();
        $objectManagerFactory = ObjectManagerFactory::withDefaults($queryHelper, $inflector, $reader);
        $instance = new static($queryHelper, $repositoryFactory, $objectManagerFactory, $inflector);
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
        if (class_exists($className)) {
            return new $className($db, $cache);
        }

        return new QueryHelper($db, $cache);
    }

    /**
     * ObjectMapper constructor.
     * @param QueryHelperInterface $queryHelper
     * @param ObjectRepositoryFactoryInterface $repositoryFactory
     * @param ObjectManagerFactory $objectManagerFactory
     * @param Inflector $inflector
     */
    public function __construct(QueryHelperInterface $queryHelper, ObjectRepositoryFactoryInterface $repositoryFactory, ObjectManagerFactory $objectManagerFactory, Inflector $inflector)
    {
        $this->queryHelper = $queryHelper;
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManagerFactory = $objectManagerFactory;
        $this->inflector = $inflector;
    }

    /**
     * @param string $objectName Fully qualified object class name
     * @return Repository\ObjectRepositoryInterface
     */
    public function getRepository(string $objectName)
    {
        return $this->repositoryFactory->getRepository($objectName);
    }

    /**
     * Creates a new instance of the requested object
     *
     * @param string $objectName Fully qualified object class name
     * @param array $data Optional array of data to set on object after instantiation
     * @return object
     */
    public function create(string $objectName, array $data = [])
    {
        return $this->repositoryFactory->getRepository($objectName)->create($data);
    }

    /**
     * Find an object by id
     *
     * @param string $objectName Fully qualified object class name
     * @param string|int $id
     * @param bool $useCache Use cache?
     * @return object
     */
    public function find(string $objectName, string $id, bool $useCache = true)
    {
        return $this->getRepository($objectName)->find($id, $useCache);
    }

    /**
     * Find objects by ids
     *
     * @param string $objectName Fully qualified object class name
     * @param array $ids
     * @return object[]
     */
    public function findByIds(string $objectName, array $ids): array
    {
        return $this->getRepository($objectName)->findByIds($ids);
    }

    /**
     * Find all of the specified object type
     *
     * @param string $objectName Fully qualified object class name
     * @return object[]
     */
    public function findAll(string $objectName): array
    {
        return $this->getRepository($objectName)->findAll();
    }

    /**
     * @param string $objectName Fully qualified object class name
     * @param array $criteria column => value pairs
     * @param array $orderBy column => order pairs
     * @param int $limit Maximum results to return
     * @param int $offset First result to return
     * @return object[]
     *
     * @see QueryHelperInterface::processWhereQuery() For details on $criteria
     */
    public function findBy(string $objectName, array $criteria, ?array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->getRepository($objectName)->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Finds a single object by any criteria
     *
     * @param string $objectName Fully qualified object class name
     * @param array $criteria column => value pairs
     * @return object
     *
     * @see QueryHelperInterface::processWhereQuery() For details on $criteria
     */
    public function findOneBy(string $objectName, array $criteria)
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
     * @param object[] $objects
     * @param string $className Class name of foreign object to load
     * @param string $foreignIdColumn Column / property on this object that relates to the foreign table's id (defaults to if the class = ForeignObject foreignObjectId)
     * @param string $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadOne(array $objects, string $className, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        $objectsByClass = $this->groupByClass($objects);

        $loader = $this->getRelationshipLoader();
        $loadedObjects = [];
        foreach ($objectsByClass as $class => $classObjects) {
            $loadedObjects += $loader->loadOne($classObjects, $className, $foreignIdColumn, $setter);
        }
        return $loadedObjects;
    }

    /**
     * Loads a foreign relationship where a column on the foreign object references the id for the supplied objects.
     * Used to load the "many" side of a one-to-many relationship.
     *
     * This works on objects of mixed type, although they must have exactly the same $foreignColumn, or use the default.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $foreignColumn Column / property on foreign object that relates to this object id
     * @param string $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadMany(array $objects, string $className, ?string $foreignColumn = null, ?string $setter = null): array
    {
        $objectsByClass = $this->groupByClass($objects);

        $loader = $this->getRelationshipLoader();
        $loadedObjects = [];
        foreach ($objectsByClass as $class => $classObjects) {
            $loadedObjects += $loader->loadMany($classObjects, $className, $foreignColumn, $setter);
        }
        return $loadedObjects;
    }

    /**
     * Loads objects of the foreign class onto the supplied objects linked by a link table containing the id's of both objects.
     *
     * This works theoretically on objects of mixed type, although they must have the same link table, which makes this in reality only usable
     * by for objects of the same class.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     * @param string $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadManyToMany(array $objects, string $className, string $linkTable, ?string $idColumn = null, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        $objectsByClass = $this->groupByClass($objects);

        $loader = $this->getRelationshipLoader();
        $loadedObjects = [];
        foreach ($objectsByClass as $class => $classObjects) {
            $loadedObjects += $loader->loadManyToMany($classObjects, $className, $linkTable, $idColumn, $foreignIdColumn, $setter);
        }
        return $loadedObjects;
    }

    /**
     * Persists a single object to the database
     *
     * @param object $object
     * @return object
     */
    public function save($object)
    {
        return $this->getRepository(get_class($object))->save($object);
    }

    /**
     * Persists all objects to the database.
     *
     * This method works on objects of mixed type.
     *
     * @param object[] $objects
     */
    public function saveAll(array $objects)
    {
        $objectsByClass = $this->groupByClass($objects);

        foreach ($objectsByClass as $class => $classObjects) {
            $this->getRepository($class)->saveAll($classObjects);
        }
    }

    /**
     * @param object $object
     */
    public function delete($object)
    {
        $this->getRepository(get_class($object))->delete($object);
    }

    /**
     * Delete all objects from the database by their ids.
     *
     * This method works on objects of mixed type.
     *
     * @param object[] $objects
     */
    public function deleteAll(array $objects)
    {
        $objectsByClass = $this->groupByClass($objects);

        foreach ($objectsByClass as $class => $classObjects) {
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
     * @return Inflector
     */
    public function getInflector()
    {
        return $this->inflector;
    }
    
    /**
     * @return RelationshipLoader
     */
    public function getRelationshipLoader()
    {
        if ($this->relationshipLoader) {
            return $this->relationshipLoader;
        }
        return $this->relationshipLoader = new RelationshipLoader($this);
    }

    /**
     * @return RelationshipSaver
     */
    public function getRelationshipSaver()
    {
        if ($this->relationshipSaver) {
            return $this->relationshipSaver;
        }
        return $this->relationshipSaver = new RelationshipSaver($this);
    }

    /**
     * @return UnitOfWork
     */
    public function unitOfWork()
    {
        return new UnitOfWork($this);
    }

    /**
     * @return ObjectManagerFactory
     */
    public function getObjectManagerFactory()
    {
        return $this->objectManagerFactory;
    }

    /**
     * @param string|object|array $objectOrClass
     * @return DataObject\ObjectManager
     */
    public function getObjectManager($objectOrClass)
    {
        if (is_array($objectOrClass)) {
            $objectOrClass = reset($objectOrClass);
        }
        $class = is_string($objectOrClass) ? $objectOrClass : get_class($objectOrClass);
        return $this->getRepository($class)->getObjectManager();
    }

    /**
     * @param object[] $objects
     * @return array
     */
    protected function groupByClass(array $objects)
    {
        $objectsByClass = [];
        foreach ($objects as $object) {
            $objectsByClass[get_class($object)][] = $object;
        }
        return $objectsByClass;
    }
}
