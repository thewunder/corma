<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectEvent;
use Corma\DataObject\ObjectManager;
use Corma\Exception\ClassNotFoundException;
use Corma\Exception\InvalidArgumentException;
use Corma\ObjectMapper;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Util\OffsetPagedQuery;
use Corma\Util\PagedQuery;
use Corma\Util\SeekPagedQuery;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * An object repository manages all creation, persistence, retrieval, deletion of objects of a particular class.
 *
 * This base class is used when all no repository is defined for a class, and is the base for all other repository classes.
 */
class ObjectRepository implements ObjectRepositoryInterface
{
    /**
     * @var Connection
     */
    protected $db;
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var QueryHelperInterface
     */
    protected $queryHelper;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $shortClassName;

    /**
     * @var CacheProvider
     */
    protected $cache;

    /**
     * @var ObjectMapper
     */
    protected $objectMapper;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var CacheProvider
     */
    protected $identityMap;

    /**
     * @var array Array of dependencies passed as constructor parameters to the data objects
     */
    protected $objectDependencies = [];

    public function __construct(Connection $db, ObjectMapper $objectMapper, CacheProvider $cache, EventDispatcherInterface $dispatcher = null)
    {
        $this->db = $db;
        $this->objectMapper = $objectMapper;
        $this->queryHelper = $objectMapper->getQueryHelper();
        $this->cache = $cache;
        $this->dispatcher = $dispatcher;
    }

    public function create(array $data = []): object
    {
        return $this->getObjectManager()->create($data);
    }

    public function find($id, bool $useCache = true): ?object
    {
        $identityMap = $this->getIdentityMap();
        if ($useCache && $identityMap->contains($id)) {
            return $identityMap->fetch($id);
        }

        $identifier = $this->getObjectManager()->getIdColumn();
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['main.'.$identifier => $id]);
        $instance = $this->fetchOne($qb);
        if ($instance) {
            $identityMap->save($id, $instance);
        }
        return $instance;
    }

    public function findByIds(array $ids, bool $useCache = true): array
    {
        $om = $this->getObjectManager();
        if ($useCache) {
            $instances = $this->getIdentityMap()->fetchMultiple($ids);
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

    public function findAll(): iterable
    {
        $om = $this->getObjectManager();
        $qb = $this->queryHelper->buildSelectQuery($om->getTable());
        $all = $this->fetchAll($qb);

        $this->storeInIdentityMap($all);

        return $all;
    }

    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): iterable
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

    public function getClassName(): string
    {
        if ($this->className) {
            return $this->className;
        }

        $class = explode('\\', get_called_class());
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
     * @param string $className
     * @return $this
     */
    public function setClassName(string $className)
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

    public function save(object $object, ?\Closure $saveRelationships = null): object
    {
        $this->checkArgument($object);

        $saveRelationships = func_num_args() === 1 ? $this->saveRelationships() : $saveRelationships;

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

    public function saveAll(array $objects, ?\Closure $saveRelationships = null): int
    {
        if (empty($objects)) {
            return 0;
        }

        $om = $this->getObjectManager();

        foreach ($objects as $object) {
            $this->checkArgument($object);
            $this->dispatchEvents('beforeSave', $object);
            if ($om->getId($object)) {
                $this->dispatchEvents('beforeUpdate', $object);
            } else {
                $this->dispatchEvents('beforeInsert', $object);
            }
        }

        $lastId = null;
        $saveRelationships = $saveRelationships ?? $this->saveRelationships();
        $doUpsert = function () use ($objects, $om, $saveRelationships, &$lastId) {
            $columns = $this->queryHelper->getDbColumns($this->getTableName());
            $rows = [];
            foreach ($objects as $object) {
                $data = $om->extract($object);
                foreach ($data as $prop => $value) {
                    if (!$columns->hasColumn($prop)) {
                        unset($data[$prop]);
                    }
                }
                $rows[] = $data;
            }

            $rows = $this->queryHelper->massUpsert($this->getTableName(), $rows, $lastId);
            if ($saveRelationships) {
                $saveRelationships($objects);
            }
            return $rows;
        };

        if ($saveRelationships) {
            $rows = $this->objectMapper->unitOfWork()->executeTransaction($doUpsert);
        } else {
            $rows = $doUpsert();
        }

        foreach ($objects as $object) {
            if ($om->getId($object)) {
                $this->dispatchEvents('afterUpdate', $object);
            } else {
                if ($lastId) {
                    $om->setId($object, $lastId);
                    $lastId++;
                }
                $this->dispatchEvents('afterInsert', $object);
            }
            $this->dispatchEvents('afterSave', $object);
        }

        return $rows ?? 0;
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

    protected function getIdentityMap(): CacheProvider
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
        return $this->getIdentityMap()->saveMultiple($toCache);
    }

    /**
     * Inserts this DataObject into the database
     *
     * @param object $object
     * @param \Closure|null $saveRelationships
     * @return object The newly persisted object with id set
     */
    protected function insert(object $object, ?\Closure $saveRelationships): object
    {
        $this->dispatchEvents('beforeInsert', $object);

        $data = $this->buildQueryParams($object);
        $this->queryHelper->massInsert($this->getTableName(), [$data]);

        $this->getObjectManager()->setNewId($object);
        if ($saveRelationships) {
            $saveRelationships([$object]);
        }

        $this->dispatchEvents('afterInsert', $object);
        return $object;
    }

    /**
     *  Update this DataObject's table row
     *
     * @param object $object
     * @param \Closure|null $saveRelationships
     */
    protected function update(object $object, ?\Closure $saveRelationships)
    {
        $this->dispatchEvents('beforeUpdate', $object);

        $om = $this->getObjectManager();
        $data = $this->buildQueryParams($object);
        $this->queryHelper->massUpdate($this->getTableName(), $data, [$om->getIdColumn() =>$om->getId($object)]);
        if ($saveRelationships) {
            $saveRelationships([$object]);
        }

        $this->dispatchEvents('afterUpdate', $object);
    }

    /**
     * Override this method to return a closure that takes an array of objects and saves child relationships using the
     * RelationshipSaver
     *
     * @return \Closure|null
     */
    protected function saveRelationships(): ?\Closure
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
     * @param object $object
     * @param callable $afterSave
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
     * @param callable $afterSave
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
     * @param QueryBuilder $qb
     * @param int $pageSize
     * @param string $strategy
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
     * @param object $object
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
     * @param QueryBuilder $qb
     * @return object[]
     */
    protected function fetchAll(QueryBuilder $qb)
    {
        $statement = $qb->execute();
        $objects = $this->getObjectManager()->fetchAll($statement);
        foreach ($objects as $object) {
            $this->dispatchEvents('loaded', $object);
        }
        return $objects;
    }

    /**
     * @param QueryBuilder $qb
     * @return object
     */
    protected function fetchOne(QueryBuilder $qb)
    {
        $statement = $qb->setMaxResults(1)->execute();
        $object = $this->getObjectManager()->fetchOne($statement);
        if ($object) {
            $this->dispatchEvents('loaded', $object);
            return $object;
        } else {
            return null;
        }
    }

    /**
     * Dispatches two events one generic DataObject one and a class specific event
     *
     * @param string $eventName
     * @param object $object
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
    protected function getShortClassName()
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
     * @param array $data
     * @return object
     */
    protected function restoreFromCache(array $data)
    {
        $object = $this->create($data);
        $om = $this->getObjectManager();
        $this->getIdentityMap()->save($om->getId($object), $object);
        return $object;
    }

    /**
     * Restores objects from the cache at the key specified
     *
     * @param string $key Cache key
     * @return object[]
     */
    protected function restoreAllFromCache(string $key)
    {
        $cachedData = $this->cache->fetch($key);
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
     * @param string $key
     * @param int $lifeTime
     */
    protected function storeAllInCache(array $objects, string $key, $lifeTime = 0): void
    {
        $dataToCache = [];
        $om = $this->getObjectManager();
        foreach ($objects as $object) {
            $dataToCache[] = $om->extract($object);
        }
        $this->storeInIdentityMap($objects);
        $this->cache->save($key, $dataToCache, $lifeTime);
    }

    /**
     * @param object $object
     */
    protected function checkArgument(object $object): void
    {
        $className = $this->getClassName();
        if (!($object instanceof $className)) {
            throw new InvalidArgumentException("Object must be instance of $className");
        }
    }
}
