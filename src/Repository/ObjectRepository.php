<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectInterface;
use Corma\DataObject\Event;
use Corma\Exception\ClassNotFoundException;
use Corma\Exception\InvalidClassException;
use Corma\Util\QueryHelper;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @var QueryHelper
     */
    private $queryHelper;

    /**
     * @var Cache
     */
    protected $cache;

    protected $objectByIdCache;

    /**
     * @var array Array of dependencies passed as constructor parameters to the data objects
     */
    protected $objectDependencies = [];

    public function __construct(Connection $db, EventDispatcherInterface $dispatcher, QueryHelper $queryHelper, Cache $cache)
    {
        $this->db = $db;
        $this->dispatcher = $dispatcher;
        $this->queryHelper = $queryHelper;
        $this->cache = $cache;
    }

    public function create()
    {
        $class = $this->getClassName();
        if(empty($this->objectDependencies)) {
            return new $class();
        } else {
            $reflectionClass = new \ReflectionClass($class);
            return $reflectionClass->newInstanceArgs($this->objectDependencies);
        }
    }

    public function find($id, $useCache = true)
    {
        if($useCache && isset($this->objectByIdCache[$id])) {
            return $this->objectByIdCache[$id];
        }
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['main.id'=>$id]);
        $instance = $this->fetchOne($qb);
        if($instance) {
            $this->dispatchEvents('afterLoad', $instance);
            $this->objectByIdCache[$id] = $instance;
        }
        return $instance;
    }

    /**
     * Find one or more data objects by id
     *
     * @param array $ids
     * @param bool $useCache
     * @return array
     */
    public function findByIds(array $ids, $useCache = true)
    {
        $instances = [];
        if($useCache) {
            foreach($ids as $i => $id) {
                if(isset($this->objectByIdCache[$id])) {
                    $instances[] = $this->objectByIdCache[$id];
                    unset($ids[$i]);
                }
            }
        }

        if(!empty($ids)) {
            $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['main.id'=>$ids]);
            $newInstances = $this->fetchAll($qb);
            /** @var $instance DataObjectInterface */
            foreach($newInstances as $instance) {
                $this->objectByIdCache[$instance->getId()] = $instance;
            }
            $instances = array_merge($instances, $newInstances);
        }

        return $instances;
    }

    public function findAll()
    {
        $dbColumns = $this->queryHelper->getDbColumns($this->getTableName());
        if(isset($dbColumns['isDeleted'])) {
            $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['isDeleted'=>0]);
        } else {
            $qb = $this->queryHelper->buildSelectQuery($this->getTableName());
        }
        $all = $this->fetchAll($qb);
        array_walk($all, function(DataObjectInterface $object){
            $this->objectByIdCache[$object->getId()] = $object;
        });
        return $all;
    }

    public function findBy(array $criteria, array $orderBy = [], $limit = null, $offset = null)
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', $criteria, $orderBy);
        if($limit) {
            $qb->setMaxResults($limit);
            if($offset) {
                $qb->setFirstResult($offset);
            }
        }
        return $this->fetchAll($qb);
    }

    public function findOneBy(array $criteria)
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', $criteria);
        $qb->setMaxResults(1);
        return $this->fetchOne($qb);
    }

    /**
     * Returns the full class name of the object managed by the repository.
     *
     * @return string
     */
    public function getClassName()
    {
        $class = explode('\\', get_called_class());
        $objectClass = [];
        foreach($class as $classPart) {
            if($classPart != 'Repository') {
                $objectClass[] = str_replace('Repository', '', $classPart);
            }
        }
        return implode('\\', $objectClass);
    }

    /**
     * Return the database table this repository manages
     *
     * @return string
     */
    public function getTableName()
    {
        $class = $this->getClassName();
        if(!class_exists($class)) {
            throw new ClassNotFoundException("$class not found");
        } else if(!class_implements($class, DataObjectInterface::class)) {
            throw new InvalidClassException("$class must implement DataObjectInterface");
        }
        return $class::getTableName();
    }

    /**
     * Persists the object to the database
     *
     * @param DataObjectInterface $object
     * @return DataObjectInterface
     */
    public function save(DataObjectInterface $object)
    {
        $this->dispatchEvents('beforeSave', $object);

        if($object->getId()) {
            $this->update($object);
        } else {
            $this->insert($object);
        }

        $this->dispatchEvents('afterSave', $object);
        return $object;
    }

    /**
     * Removes the object from the database
     *
     * @param DataObjectInterface $object
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function delete(DataObjectInterface $object)
    {
        $this->dispatchEvents('beforeDelete', $object);

        $columns = $this->queryHelper->getDbColumns($object->getTableName());

        if(isset($columns['isDeleted'])) {
            $this->db->update($object->getTableName(), ['isDeleted'=>1], ['id'=>$object->getId()]);
        } else {
            $this->db->delete($object->getTableName(), ['id'=>$object->getId()]);
        }

        $object->setIsDeleted(true);

        $this->dispatchEvents('afterDelete', $object);
    }

    /**
     * Inserts this DataObject into the database
     *
     * @param DataObjectInterface $object
     * @return DataObjectInterface The newly persisted object with id set
     */
    protected function insert(DataObjectInterface $object)
    {
        $this->dispatchEvents('beforeInsert', $object);

        $queryParams = $this->buildQueryParams($object);

        $this->db->insert($object->getTableName(), $queryParams);

        $object->setId($this->db->lastInsertId());

        $this->dispatchEvents('afterInsert', $object);
        return $object;
    }

    /**
     *  Update this DataObject's table row
     *
     * @param DataObjectInterface $object
     */
    protected function update(DataObjectInterface $object)
    {
        $this->dispatchEvents('beforeUpdate', $object);

        $queryParams = $this->buildQueryParams($object);

        $this->db->update($object->getTableName(), $queryParams, ['id'=>$object->getId()]);

        $this->dispatchEvents('afterUpdate', $object);
    }

    /**
     * Build parameters for insert or update
     * @param DataObjectInterface $object
     * @return array
     */
    protected function buildQueryParams(DataObjectInterface $object)
    {
        $queryParams = [];
        $dbColumns = $this->queryHelper->getDbColumns($object->getTableName());
        foreach ($dbColumns as $column => $acceptNull) {
            $value = $this->getValue($object, $column);
            if (isset($dbColumns[$column])) {
                if ($column == 'id') {
                    continue;
                } else if($value === null && $acceptNull === false) {
                    continue;
                } else {
                    $queryParams[$this->db->quoteIdentifier($column)] = $value;
                }
            }
        }
        return $queryParams;
    }

    private function getValue(DataObjectInterface $object, $column)
    {
        $getter = ucfirst($column);
        $getter = "get{$getter}";
        if(method_exists($object, $getter)) {
            return $object->$getter();
        } else if(property_exists($object, $column)){
            return $object->{$column};
        }
        return null;
    }

    /**
     * @param QueryBuilder $qb
     * @return DataObjectInterface[]
     */
    protected function fetchAll(QueryBuilder $qb)
    {
        /** @var Statement $statement */
        $statement = $qb->execute();
        return $statement->fetchAll(\PDO::FETCH_CLASS, $this->getClassName(), $this->objectDependencies);
    }

    /**
     * @param QueryBuilder $qb
     * @return DataObjectInterface
     */
    protected function fetchOne(QueryBuilder $qb)
    {
        $statement = $qb->setMaxResults(1)->execute();
        $statement->setFetchMode(\PDO::FETCH_CLASS, $this->getClassName(), $this->objectDependencies);
        return $results = $statement->fetch();
    }

    /**
     * Is this exception caused by a duplicate record (i.e. unique index constraint violation)
     * Probably only works with mysql
     *
     * @param \Exception $error
     * @return bool
     */
    public static function isDuplicateException(\Exception $error)
    {
        /** @var \PDOException $previous */
        $previous = $error->getPrevious();
        if(!$previous || $previous->getCode() != 23000) {
            return false;
        }
        return isset($previous->errorInfo[1]) && $previous->errorInfo[1] == 1062;
    }

    /**
     * Dispatches two events one generic DataObject one and a class specific event
     *
     * @param string $eventName
     * @param DataObjectInterface $object
     */
    protected function dispatchEvents($eventName, DataObjectInterface $object)
    {
        $this->dispatcher->dispatch('DataObject.'.$eventName, new Event($object));
        $class = $object->getClassName();
        $this->dispatcher->dispatch('DataObject.'.$class.'.'.$eventName, new Event($object));
    }
}