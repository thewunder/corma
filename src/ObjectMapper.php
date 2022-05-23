<?php
namespace Corma;

use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\QueryHelper\QueryModifier\SoftDelete;
use Corma\Relationship\RelationshipSaver;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\QueryHelper\QueryHelper;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Relationship\RelationshipLoader;
use Corma\Repository\ObjectRepositoryInterface;
use Corma\Util\Inflector;
use Corma\Util\LimitedArrayCache;
use Corma\Util\UnitOfWork;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Main entry point for the ORM
 */
class ObjectMapper
{
    private ?RelationshipLoader $relationshipLoader = null;
    private ?RelationshipSaver $relationshipSaver = null;

    /**
     * Creates a ObjectMapper instance using the default QueryHelper and ObjectRepositoryFactory
     *
     * @param Connection $db Database connection
     * @param ContainerInterface $container Dependency injection container for constructing data objects and their repositories
     * @param CacheInterface|null $cache Cache for table metadata and repositories
     * @param EventDispatcherInterface|null $dispatcher
     * @return self
     *
     */
    public static function withDefaults(Connection                $db,
                                        ContainerInterface $container,
                                        ?CacheInterface          $cache = null,
                                        ?EventDispatcherInterface $dispatcher = null): self
    {
        if ($cache === null) {
            $cache = new LimitedArrayCache(10000);
        }

        $queryHelper = self::createQueryHelper($db, $cache);
        $queryHelper->addModifier(new SoftDelete($queryHelper));

        $inflector = Inflector::build();

        $repositoryFactory = new ObjectRepositoryFactory($container);
        $objectManagerFactory = ObjectManagerFactory::withDefaults($queryHelper, $inflector, $container);

        $instance = new self($queryHelper, $repositoryFactory, $objectManagerFactory, $inflector);

        $repositoryDependencies = [$db, $instance, $cache, $dispatcher];
        if (!empty($dependencies)) {
            $repositoryDependencies = array_merge($repositoryDependencies, $dependencies);
        }
        $repositoryFactory->setDependencies($repositoryDependencies);

        return $instance;
    }

    /**
     * @param Connection $db
     * @param CacheInterface $cache
     * @return QueryHelperInterface
     */
    protected static function createQueryHelper(Connection $db, CacheInterface $cache): QueryHelperInterface
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
    public function __construct(private QueryHelperInterface $queryHelper, private ObjectRepositoryFactoryInterface $repositoryFactory,
                                private ObjectManagerFactory $objectManagerFactory, private Inflector $inflector)
    {
    }

    /**
     * @param string $objectName Fully qualified object class name
     * @return Repository\ObjectRepositoryInterface
     */
    public function getRepository(string $objectName): ObjectRepositoryInterface
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
    public function create(string $objectName, array $data = []): object
    {
        return $this->repositoryFactory->getRepository($objectName)->create($data);
    }

    /**
     * Find an object by id
     *
     * @param string $objectName Fully qualified object class name
     * @param string|int $id
     * @param bool $useCache Use cache?
     * @return object|null
     */
    public function find(string $objectName, string|int $id, bool $useCache = true): ?object
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
     * @param array|null $orderBy column => order pairs
     * @param int|null $limit Maximum results to return
     * @param int|null $offset First result to return
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
     * @param array|null $orderBy column => order pairs
     * @return object|null
     *
     * @see QueryHelperInterface::processWhereQuery() For details on $criteria
     */
    public function findOneBy(string $objectName, array $criteria, ?array $orderBy = []): ?object
    {
        return $this->getRepository($objectName)->findOneBy($criteria, $orderBy);
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
     * @param string|null $foreignIdColumn Column / property on this object that relates to the foreign table's id (defaults to if the class = ForeignObject foreignObjectId)
     * @param string|null $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadOne(array $objects, string $className, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        $objectsByClass = $this->groupByClass($objects);

        $loader = $this->getRelationshipLoader();
        $loadedObjects = [];
        foreach ($objectsByClass as $classObjects) {
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
     * @param string|null $foreignColumn Column / property on foreign object that relates to this object id
     * @param string|null $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadMany(array $objects, string $className, ?string $foreignColumn = null, ?string $setter = null): array
    {
        $objectsByClass = $this->groupByClass($objects);

        $loader = $this->getRelationshipLoader();
        $loadedObjects = [];
        foreach ($objectsByClass as $classObjects) {
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
     * @param string|null $idColumn Column on link table = the id on this object
     * @param string|null $foreignIdColumn Column on link table = the id on the foreign object table
     * @param string|null $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadManyToMany(array $objects, string $className, string $linkTable, ?string $idColumn = null, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        $objectsByClass = $this->groupByClass($objects);

        $loader = $this->getRelationshipLoader();
        $loadedObjects = [];
        foreach ($objectsByClass as $classObjects) {
            $loadedObjects += $loader->loadManyToMany($classObjects, $className, $linkTable, $idColumn, $foreignIdColumn, $setter);
        }
        return $loadedObjects;
    }

    /**
     * Persists a single object to the database
     *
     * @param object $object The object to save
     * @param \Closure|null $saveRelationships If the repository provides a saveRelationships closure then omitting
     * will use the default specified by the repository. Explicitly passing null will not save any relationships even
     * if the repository returns a closure from saveRelationships.
     * @return object
     */
    public function save(object $object, ?\Closure $saveRelationships = null): object
    {
        $repository = $this->getRepository(get_class($object));
        if (func_num_args() == 2) {
            return $repository->save($object, $saveRelationships);
        } else {
            return $repository->save($object);
        }
    }

    /**
     * Persists all objects to the database.
     *
     * This method works on objects of mixed type.
     *
     * @param object[] $objects The objects to save
     * @param \Closure|null $saveRelationships If the repository provides a saveRelationships closure then omitting
     * will use the default specified by the repository. Explicitly passing null will not save any relationships even
     * if the repository returns a closure from saveRelationships.
     */
    public function saveAll(array $objects, ?\Closure $saveRelationships = null): void
    {
        $objectsByClass = $this->groupByClass($objects);

        foreach ($objectsByClass as $class => $classObjects) {
            $repository = $this->getRepository($class);

            if (func_num_args() == 2) {
                $repository->saveAll($classObjects, $saveRelationships);
            } else {
                $repository->saveAll($classObjects);
            }
        }
    }

    /**
     * @param object $object Object to delete
     */
    public function delete(object $object): void
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
    public function deleteAll(array $objects): void
    {
        $objectsByClass = $this->groupByClass($objects);

        foreach ($objectsByClass as $class => $classObjects) {
            $this->getRepository($class)->deleteAll($classObjects);
        }
    }

    public function getQueryHelper(): QueryHelperInterface
    {
        return $this->queryHelper;
    }

    public function getInflector(): Inflector
    {
        return $this->inflector;
    }

    public function getRelationshipLoader(): RelationshipLoader
    {
        if ($this->relationshipLoader) {
            return $this->relationshipLoader;
        }
        return $this->relationshipLoader = new RelationshipLoader($this);
    }

    public function getRelationshipSaver(): RelationshipSaver
    {
        if ($this->relationshipSaver) {
            return $this->relationshipSaver;
        }
        return $this->relationshipSaver = new RelationshipSaver($this);
    }

    public function unitOfWork(): UnitOfWork
    {
        return new UnitOfWork($this);
    }

    public function getObjectManagerFactory(): ObjectManagerFactory
    {
        return $this->objectManagerFactory;
    }

    public function getIdentityMap(): CacheInterface
    {
        return new LimitedArrayCache();
    }

    /**
     * @param object|array|string $objectOrClass
     * @return DataObject\ObjectManager
     */
    public function getObjectManager(object|string|array $objectOrClass): ObjectManager
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
    protected function groupByClass(array $objects): array
    {
        $objectsByClass = [];
        foreach ($objects as $object) {
            $objectsByClass[get_class($object)][] = $object;
        }
        return $objectsByClass;
    }
}
