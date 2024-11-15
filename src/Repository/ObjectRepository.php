<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectEvent;
use Corma\DataObject\ObjectManager;
use Corma\Exception\ClassNotFoundException;
use Corma\Exception\InvalidArgumentException;
use Corma\Exception\InvalidAttributeException;
use Corma\ObjectMapper;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Relationship\JoinType;
use Corma\Relationship\PolymorphicHandler;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\OffsetPagedQuery;
use Corma\Util\PagedQuery;
use Corma\Util\SeekPagedQuery;
use Corma\DBAL\Connection;
use Corma\DBAL\Exception;
use Corma\DBAL\Query\QueryBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * An object repository manages all creation, persistence, retrieval, deletion of objects of a particular class.
 *
 * This base class is used when all no repository is defined for a class, and is the base for all other repository classes.
 */
class ObjectRepository implements ObjectRepositoryInterface
{
    protected QueryHelperInterface $queryHelper;
    protected ?string $className = null;
    protected ?string $shortClassName = null;
    protected ?ObjectManager $objectManager = null;
    protected ?CacheInterface $identityMap = null;

    /**
     * @var array Array of dependencies passed as constructor parameters to the data objects
     */
    protected array $objectDependencies = [];

    public function __construct(protected Connection    $db, protected ObjectMapper $objectMapper,
                                protected CacheInterface $cache, protected ?EventDispatcherInterface $dispatcher = null)
    {
        $this->queryHelper = $objectMapper->getQueryHelper();
    }



    public function create(array $data = []): object
    {
        return $this->getObjectManager()->create($data);
    }

    public function find($id, bool $useCache = true): ?object
    {
        $identityMap = $this->getIdentityMap();
        if ($useCache && $identityMap->has($id)) {
            return $identityMap->get($id);
        }

        $identifier = $this->getObjectManager()->getIdColumn();
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['main.'.$identifier => $id]);
        $instance = $this->fetchOne($qb);
        if ($instance) {
            $identityMap->set($id, $instance);
        }
        return $instance;
    }

    public function findByIds(array $ids, bool $useCache = true): array
    {
        $om = $this->getObjectManager();
        if ($useCache) {
            $instances = array_filter($this->getIdentityMap()->getMultiple($ids));
            $cachedIds = [];
            foreach ($instances as $instance) {
                $cachedIds[] = $om->getId($instance);
            }
            $ids = array_diff($ids, $cachedIds);
        } else {
            $instances = [];
        }

        if (!empty($ids)) {
            $identifier = $om->getIdColumn();
            $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['main.'.$identifier => $ids]);

            $newInstances = $this->fetchAll($qb);

            $this->storeInIdentityMap($newInstances);

            $instances = array_merge($instances, $newInstances);
        }

        return $instances;
    }

    public function findAll(): array
    {
        $om = $this->getObjectManager();
        $qb = $this->queryHelper->buildSelectQuery($om->getTable());
        $all = $this->fetchAll($qb);

        $this->storeInIdentityMap($all);

        return $all;
    }

    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', $criteria, $orderBy);
        if ($limit) {
            $qb->setMaxResults($limit);
            if ($offset) {
                $qb->setFirstResult($offset);
            }
        }
        return $this->fetchAll($qb);
    }

    public function findOneBy(array $criteria, array $orderBy = []): ?object
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', $criteria, $orderBy);
        $qb->setMaxResults(1);
        return $this->fetchOne($qb);
    }

    public function count(array $criteria = []): int
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', $criteria);
        return $this->queryHelper->getCount($qb, $this->objectManager->getIdColumn());
    }

    public function getClassName(): string
    {
        if ($this->className) {
            return $this->className;
        }

        $class = explode('\\', static::class);
        $objectClass = [];
        foreach ($class as $classPart) {
            if ($classPart != 'Repository') {
                $objectClass[] = str_replace('Repository', '', $classPart);
            }
        }
        $this->className = implode('\\', $objectClass);

        if (!class_exists($this->className)) {
            throw new ClassNotFoundException("$this->className not found");
        }
        return $this->className;
    }

    /**
     * Set the class name, this allows the base class to be used for data objects without a repository
     *
     * This method is deliberately excluded from the interface.
     *
     * @return $this
     */
    public function setClassName(string $className): static
    {
        $this->className = $className;
        return $this;
    }

    public function getTableName($objectOrClass = null): string
    {
        if ($objectOrClass === null) {
            return $this->getObjectManager()->getTable();
        } else {
            return $this->objectMapper->getObjectManager($objectOrClass)->getTable();
        }
    }

    public function save(object $object, string|\Closure|null ...$saveRelationships): object
    {
        $this->checkArgument($object);

        $saveRelationships = $this->prepareRelationshipSave($saveRelationships);

        $doSave = function () use ($object, $saveRelationships) {
            $this->dispatchEvents('beforeSave', $object);
            if ($this->getObjectManager()->isNew($object)) {
                $this->insert($object, $saveRelationships);
            } else {
                $this->update($object, $saveRelationships);
            }
            $this->dispatchEvents('afterSave', $object);
        };

        if ($saveRelationships) {
            $this->objectMapper->unitOfWork()->executeTransaction($doSave);
        } else {
            $doSave();
        }

        return $object;
    }

    public function saveAll(array $objects, string|\Closure|null ...$saveRelationships): int
    {
        if (empty($objects)) {
            return 0;
        }

        $om = $this->getObjectManager();

        $inserts = [];
        $uniqueObjects = [];
        foreach ($objects as $i => $object) {
            $this->checkArgument($object);
            $hash = spl_object_hash($object);
            if (isset($uniqueObjects[$hash])) {
                continue;
            }
            $uniqueObjects[$hash] = $object;
            $this->dispatchEvents('beforeSave', $object);
            if ($om->getId($object)) {
                $this->dispatchEvents('beforeUpdate', $object);
            } else {
                $this->dispatchEvents('beforeInsert', $object);
                $inserts[$i] = $object;
            }
        }

        $saveRelationships = $this->prepareRelationshipSave($saveRelationships);

        $doUpsert = function () use ($uniqueObjects, $om, $saveRelationships, $inserts) {
            $columns = $this->queryHelper->getDbColumns($this->getTableName());
            $rows = [];
            foreach ($uniqueObjects as $object) {
                $data = $om->extract($object);
                foreach ($data as $prop => $value) {
                    if (!$columns->hasColumn($prop)) {
                        unset($data[$prop]);
                    }
                }
                $rows[] = $data;
            }

            $lastId = null;
            $rowCount = $this->queryHelper->massUpsert($this->getTableName(), $rows, $lastId);
            foreach ($inserts as $object) {
                if ($lastId) {
                    $om->setId($object, $lastId);
                    $lastId++;
                }
            }

            if (is_array($saveRelationships) && !empty($saveRelationships)) {
                $this->objectMapper->getRelationshipManager()->save($uniqueObjects, ...$saveRelationships);
            } else if ($saveRelationships instanceof \Closure) {
                $saveRelationships($uniqueObjects);
            }

            return $rowCount;
        };

        if ($saveRelationships) {
            $rows = $this->objectMapper->unitOfWork()->executeTransaction($doUpsert);
        } else {
            $rows = $doUpsert();
        }

        foreach ($objects as $i => $object) {
            if (isset($inserts[$i])) {
                $this->dispatchEvents('afterInsert', $object);
            } else {
                $this->dispatchEvents('afterUpdate', $object);
            }
            $this->dispatchEvents('afterSave', $object);
        }

        return $rows ?? 0;
    }

    /**
     * Defaults save relationship and extracts closure for backward compatibility
     */
    private function prepareRelationshipSave(string|\Closure|array $saveRelationships): array|\Closure|null
    {
        $saveRelationships = !empty($saveRelationships) ? $saveRelationships : $this->saveRelationships();
        if (is_array($saveRelationships) && count($saveRelationships) == 1 && !is_string($saveRelationships[0])) {
            $saveRelationships = $saveRelationships[0];
        }
        return $saveRelationships;
    }

    /**
     * Removes the object from the database
     *
     * @param object $object
     */
    public function delete(object $object): void
    {
        $this->checkArgument($object);
        $this->dispatchEvents('beforeDelete', $object);

        $om = $this->getObjectManager();
        $table = $om->getTable();
        $idColumn = $om->getIdColumn();
        $id = $om->getId($object);

        $this->queryHelper->massDelete($table, [$idColumn=>$id]);

        $this->dispatchEvents('afterDelete', $object);
    }

    /**
     * Deletes all objects by id
     *
     * @param object[] $objects
     * @return int Number of db rows effected
     */
    public function deleteAll(array $objects): int
    {
        if (empty($objects)) {
            return 0;
        }

        foreach ($objects as $object) {
            $this->checkArgument($object);
            $this->dispatchEvents('beforeDelete', $object);
        }

        $om = $this->getObjectManager();
        $idColumn = $om->getIdColumn();
        $ids = $om->getIds($objects);

        $rows = $this->queryHelper->massDelete($this->getTableName(), [$idColumn=>$ids]);

        foreach ($objects as $object) {
            $this->dispatchEvents('afterDelete', $object);
        }

        return $rows;
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager(): ObjectManager
    {
        if ($this->objectManager) {
            return $this->objectManager;
        }
        $objectManagerFactory = $this->objectMapper->getObjectManagerFactory();
        return $this->objectManager = $objectManagerFactory->getManager($this->getClassName(), $this->objectDependencies);
    }

    protected function getIdentityMap(): CacheInterface
    {
        if ($this->identityMap) {
            return $this->identityMap;
        }

        return $this->identityMap = $this->objectMapper->getIdentityMap();
    }

    protected function storeInIdentityMap(array $objects): bool
    {
        $om = $this->getObjectManager();
        $toCache = [];
        foreach ($objects as $object) {
            $toCache[$om->getId($object)] = $object;
        }
        return $this->getIdentityMap()->setMultiple($toCache);
    }

    /**
     * Inserts this DataObject into the database
     *
     * @param array|\Closure|null $saveRelationships
     * @return object The newly persisted object with id set
     */
    protected function insert(object $object, array|\Closure|null $saveRelationships): object
    {
        $this->dispatchEvents('beforeInsert', $object);

        $data = $this->buildQueryParams($object);
        $this->queryHelper->massInsert($this->getTableName(), [$data]);

        $this->getObjectManager()->setNewId($object);

        if (is_array($saveRelationships) && !empty($saveRelationships)) {
            $this->objectMapper->getRelationshipManager()->save([$object], ...$saveRelationships);
        } else if ($saveRelationships instanceof \Closure) {
            $saveRelationships([$object]);
        }

        $this->dispatchEvents('afterInsert', $object);
        return $object;
    }

    /**
     *  Update this DataObject's table row
     *
     * @param array|\Closure|null $saveRelationships
     */
    protected function update(object $object, array|\Closure|null $saveRelationships)
    {
        $this->dispatchEvents('beforeUpdate', $object);

        $om = $this->getObjectManager();
        $data = $this->buildQueryParams($object);
        $this->queryHelper->massUpdate($this->getTableName(), $data, [$om->getIdColumn() =>$om->getId($object)]);

        if (is_array($saveRelationships) && !empty($saveRelationships)) {
            $this->objectMapper->getRelationshipManager()->save([$object], ...$saveRelationships);
        } else if ($saveRelationships instanceof \Closure) {
            $saveRelationships([$object]);
        }

        $this->dispatchEvents('afterUpdate', $object);
    }

    /**
     * Override this method to return an array of relationship names to save or a closure that takes an array of objects and saves child relationships using the
     * RelationshipSaver
     *
     * @return array|\Closure|null
     */
    protected function saveRelationships(): array|\Closure|null
    {
        return null;
    }

    /**
     * Saves the object, and executes the supplied callback, wrapping in a try / catch and transaction.
     * This meant to be used to save associated relationships when overriding the the save() method.
     *
     * Functions will receive an array parameter with the object that has just been saved
     *
     * @deprecated Use saveRelationships instead
     *
     * @param callable|null $exceptionHandler
     * @throws \Throwable
     */
    protected function saveWith(object $object, callable $afterSave, callable $exceptionHandler = null)
    {
        $this->objectMapper->unitOfWork()->executeTransaction(function () use ($object, $afterSave) {
            self::save($object);
            $afterSave([$object]);
        }, $exceptionHandler);
    }

    /**
     * Saves the objects, and executes the supplied callback, wrapping in a try / catch and transaction.
     * This meant to be used to save associated relationships when overriding the the saveAll() method.
     *
     * Functions will receive an array parameter with the objects that have just been saved
     *
     * @deprecated Use saveRelationships instead
     *
     * @param object[] $objects
     * @param callable|null $exceptionHandler
     * @throws \Throwable
     */
    protected function saveAllWith(array $objects, callable $afterSave, callable $exceptionHandler = null)
    {
        $this->objectMapper->unitOfWork()->executeTransaction(function () use ($objects, $afterSave) {
            self::saveAll($objects);
            $afterSave($objects);
        }, $exceptionHandler);
    }

    /**
     * Creates a paged query for the proved select query builder
     *
     * @return PagedQuery
     */
    protected function pagedQuery(QueryBuilder $qb, int $pageSize = PagedQuery::DEFAULT_PAGE_SIZE, string $strategy = PagedQuery::STRATEGY_OFFSET): PagedQuery
    {
        if ($strategy == PagedQuery::STRATEGY_OFFSET) {
            return new OffsetPagedQuery($qb, $this->queryHelper, $this->getObjectManager(), $pageSize);
        }

        if ($strategy == PagedQuery::STRATEGY_SEEK) {
            return new SeekPagedQuery($qb, $this->queryHelper, $this->getObjectManager(), $pageSize);
        }

        throw new InvalidArgumentException('Invalid paging strategy');
    }

    /**
     * Build parameters for insert or update
     * @return array
     */
    protected function buildQueryParams(object $object): array
    {
        $queryParams = [];
        $om = $this->getObjectManager();
        $table = $this->queryHelper->getDbColumns($om->getTable());
        $data = $om->extract($object);
        foreach ($table->getColumns() as $column) {
            $columnName = $column->getName();
            if ($columnName == $om->getIdColumn()) {
                continue;
            } if (isset($data[$columnName])) {
                $queryParams[$columnName] = $data[$columnName];
            } elseif (!$column->getNotnull()) {
                $queryParams[$columnName] = null;
            }
        }
        return $queryParams;
    }

    /**
     * @return object[]
     */
    protected function fetchAll(QueryBuilder $qb): array
    {
        $statement = $qb->executeQuery();
        $objects = $this->getObjectManager()->fetchAll($statement);
        foreach ($objects as $object) {
            $this->dispatchEvents('loaded', $object);
        }
        return $objects;
    }

    /**
     * @return object|null
     * @throws Exception
     */
    protected function fetchOne(QueryBuilder $qb): ?object
    {
        $result = $qb->setMaxResults(1)->executeQuery();
        $object = $this->getObjectManager()->fetchOne($result);
        if ($object) {
            $this->dispatchEvents('loaded', $object);
            return $object;
        } else {
            return null;
        }
    }

    /**
     * Joins to a relationship defined via property attributes.
     *
     * @param QueryBuilder $qb Query Builder to add join to
     * @param string $property Property name with relationship attribute
     * @param string|null $fromClass Full class name of the object with the relationship being joined to (defaults to the class this repository handles)
     * @param string $fromAlias Alias of table being joined from
     * @param JoinType $type Type of join (inner, left, or right)
     * @param mixed|null $additional Additional information required by the relationship type to make the join as determined by the RelationshipHandler class
     *
     * @return string The alias of the table joined to (compose of the first letter of each word in the property name)
     *
     * @throws ClassNotFoundException|InvalidAttributeException
     *
     * @see PolymorphicHandler::join()
     * @see Inflector::aliasFromProperty()
     */
    protected function join(QueryBuilder $qb, string $property, ?string $fromClass = null, string $fromAlias = 'main', JoinType $type = JoinType::INNER, mixed $additional = null): string
    {
        if (!$fromClass) {
            $fromClass = $this->getClassName();
        }
        return $this->objectMapper->getRelationshipManager()->join($qb, $fromClass, $property, $fromAlias, $type, $additional);
    }

    /**
     * Dispatches two events one generic DataObject one and a class specific event
     */
    protected function dispatchEvents(string $eventName, object $object): void
    {
        if (!$this->dispatcher) {
            return;
        }

        $event = new DataObjectEvent($object);
        $this->dispatcher->dispatch($event, 'DataObject.'.$eventName);
        $class = $this->getShortClassName();
        $this->dispatcher->dispatch($event, 'DataObject.'.$class.'.'.$eventName);
    }

    /**
     * Returns the class name minus namespace of the object managed by the repository.
     *
     * @return string
     */
    protected function getShortClassName(): string
    {
        if ($this->shortClassName) {
            return $this->shortClassName;
        }
        $class = $this->getClassName();
        return $this->shortClassName = substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * Restores a single object from cached data
     *
     * @return object
     */
    protected function restoreFromCache(array $data): object
    {
        $object = $this->create($data);
        $om = $this->getObjectManager();
        $this->getIdentityMap()->set($om->getId($object), $object);
        return $object;
    }

    /**
     * Restores objects from the cache at the key specified
     *
     * @param string $key Cache key
     * @return object[]
     */
    protected function restoreAllFromCache(string $key): array
    {
        $cachedData = $this->cache->get($key);
        $objectsFromCache = [];
        foreach ($cachedData as $data) {
            $objectsFromCache[] = $this->restoreFromCache($data);
        }
        return $objectsFromCache;
    }

    /**
     * Stores DataObjects in cache at the key specified
     *
     * @param object[] $objects
     */
    protected function storeAllInCache(array $objects, string $key, ?int $lifeTime = null): void
    {
        $dataToCache = [];
        $om = $this->getObjectManager();
        foreach ($objects as $object) {
            $dataToCache[] = $om->extract($object);
        }
        $this->storeInIdentityMap($objects);
        $this->cache->set($key, $dataToCache, $lifeTime);
    }

    protected function checkArgument(object $object): void
    {
        $className = $this->getClassName();
        if (!($object instanceof $className)) {
            throw new InvalidArgumentException("Object must be instance of $className");
        }
    }
}
