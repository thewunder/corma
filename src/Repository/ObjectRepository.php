<?php
namespace Corma\Repository;

use Corma\DataObject\DataObject;
use Corma\DataObject\Event;
use Corma\Util\QueryHelper;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Persistence\ObjectRepository as ObjectRepositoryInterface;
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
     * @var Cache
     */
    protected $cache;

    protected static $objectByIdCache;

    /**
     * @var QueryHelper
     */
    private $queryHelper;

    public function __construct(Connection $db, EventDispatcherInterface $dispatcher, QueryHelper $queryHelper)
    {
        $this->db = $db;
        $this->dispatcher = $dispatcher;
        $this->queryHelper = $queryHelper;
    }

    public function find($id, $useCache = true)
    {
        if($useCache && isset(self::$objectByIdCache[$this->getTableName()][$id])) {
            return self::$objectByIdCache[$this->getTableName()][$id];
        }
        $qb = $this->queryHelper->buildSelectQuery('main.*', ['main.id'=>$id]);
        $instance = static::fetchOne($qb);
        if($instance) {
            $this->dispatchEvents('afterLoad', $instance);
            self::$objectByIdCache[$this->getTableName()][$id] = $instance;
        }
        return $instance;
    }

    public function findAll()
    {
        // TODO: Implement findAll() method.
    }

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return array The objects.
     *
     * @throws \UnexpectedValueException
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', $criteria, $orderBy);
        return $this->fetchAll($qb);
    }

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array $criteria The criteria.
     *
     * @return object The object.
     */
    public function findOneBy(array $criteria)
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', $criteria);
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

    public function getTableName()
    {
        $class = $this->getClassName();
        if(!class_exists($class)) {
            throw new \LogicException("$class not found");
        } else if(!is_subclass_of($class, DataObject::class)) {
            throw new \LogicException("$class does not extend DataObject");
        }
        return $class::getTableName();
    }

    public function save(DataObject $object)
    {
        $this->dispatchEvents('beforeSave', $object);

        if($object->getId()) {
            $this->update($object);
        } else {
            $this->insert($object);
        }

        $this->dispatchEvents('afterSave', $object);
    }

    public function delete(DataObject $object)
    {
        $this->dispatchEvents('beforeDelete', $object);

        $columns = $this->queryHelper->getDbColumns($object);

        if(isset($columns['isDeleted'])) {
            $this->db->update($object->getTableName(), ['isDeleted'=>1], ['id'=>$object->getId()]);
        } else {
            $this->db->delete($object->getTableName(), ['id'=>$object->getId()]);
        }

        $object->setIsDeleted(true);

        $this->dispatchEvents('afterDelete', $object);
    }

    /**
     * Persist this DataObject in the database
     *
     * @param DataObject $object
     * @return DataObject The newly persisted object with id set
     */
    protected function insert(DataObject $object)
    {
        $this->dispatchEvents('beforeInsert', $object);

        $queryParams = $this->buildQueryParams($object);

        $this->db->insert($object->getTableName(), $queryParams);

        $object->setId($this->db->lastInsertId());

        $this->dispatchEvents('afterInsert', $object);
        return $object;
    }

    /**
     *  Update this DataObject's persistence
     *
     * @param DataObject $object
     */
    protected function update(DataObject $object)
    {
        $this->dispatchEvents('beforeUpdate', $object);

        $queryParams = $this->buildQueryParams($object);

        $this->db->update($object->getTableName(), $queryParams, ['id'=>$object->getId()]);

        $this->dispatchEvents('afterUpdate', $object);
    }

    /**
     * Build parameters for insert or update
     * @param DataObject $object
     * @return array
     */
    protected function buildQueryParams(DataObject $object)
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

    private function getValue(DataObject $object, $column)
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
     * @return DataObject[]
     */
    protected function fetchAll(QueryBuilder $qb)
    {
        /** @var Statement $statement */
        $statement = $qb->execute();
        return $statement->fetchAll(\PDO::FETCH_CLASS, $this->getClassName());
    }

    /**
     * @param QueryBuilder $qb
     * @return DataObject
     */
    protected function fetchOne(QueryBuilder $qb)
    {
        /** @var Statement $statement */
        $statement = $qb->setMaxResults(1)->execute();
        $results = $statement->fetchAll(\PDO::FETCH_CLASS, $this->getClassName());
        return reset($results);
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
     * @param DataObject $object
     */
    protected function dispatchEvents($eventName, DataObject $object)
    {
        $this->dispatcher->dispatch('DataObject.'.$eventName, new Event($object));
        $class = $object->getClassName();
        $this->dispatcher->dispatch('DataObject.'.$class.'.'.$eventName, new Event($object));
    }
}