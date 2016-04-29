<?php
namespace Corma\Relationship;

use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;
use Corma\Exception\MethodNotImplementedException;
use Corma\ObjectMapper;
use Corma\Util\Inflector;

/**
 * Makes saving foreign relationships easier, intended for use within ObjectRepository::save() or ObjectRepository::saveAll()
 * 
 * The supplied objects must be saved prior to calling these methods.
 * The repository is responsible for wrapping these method calls in a transaction.
 */
class RelationshipSaver
{
    /**
     * @var ObjectMapper
     */
    private $objectMapper;

    /**
     * @var Inflector
     */
    private $inflector;

    public function __construct(ObjectMapper $objectMapper)
    {
        $this->objectMapper = $objectMapper;
        $this->inflector = $objectMapper->getInflector();
    }

    /**
     * Saves a foreign relationship where a property on the supplied object references an id for another object.
     *
     * Can be used to save a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * @param DataObjectInterface[] $objects
     * @param string $foreignIdColumn Property on this object that relates to the foreign tables id
     */
    public function saveOne(array $objects, $foreignIdColumn)
    {
        $getter = 'get' . $this->inflector->methodNameFromColumn($foreignIdColumn);
        /** @var DataObjectInterface[] $foreignObjectsByObjectId */
        $foreignObjectsByObjectId = [];
        foreach($objects as $object) {
            if(!method_exists($object, $getter)) {
                throw new MethodNotImplementedException("$getter must be defined on {$object->getClassName()} to save relationship");
            }
            
            $objectIdSetter = 'set' . $this->inflector->idColumnFromClass(get_class($object));
            $foreignObject = $object->{$getter}();
            if($foreignObject) {
                $foreignObjectsByObjectId[$object->getId()] = $foreignObject;
                if(method_exists($foreignObject, $objectIdSetter)) { // for true one-to-one relationships
                    $foreignObject->{$objectIdSetter}($object->getId());
                }
            }
        }
        
        $this->objectMapper->saveAll($foreignObjectsByObjectId);
        
        $idSetter = 'set' . $foreignIdColumn;
        $idGetter = 'get' . $foreignIdColumn;
        $objectsToUpdate = [];
        foreach($objects as $object) {
            if(!method_exists($object, $idSetter)) {
                throw new MethodNotImplementedException("$idSetter must be defined on {$object->getClassName()} to save relationship");
            }
            if(!method_exists($object, $idGetter)) {
                throw new MethodNotImplementedException("$idGetter must be defined on {$object->getClassName()} to save relationship");
            }
            
            if(isset($foreignObjectsByObjectId[$object->getId()])) {
                $foreignObject = $foreignObjectsByObjectId[$object->getId()];
                if($object->{$idGetter}() != $foreignObject->getId()) {
                    $object->{$idSetter}($foreignObject->getId());
                    $objectsToUpdate[] = $object;
                }
            }
        }
        $this->objectMapper->saveAll($objectsToUpdate);
    }

    /**
     * Saves a foreign relationship where a column on another object references the id for the supplied object.
     *
     * Used to save the "many" side of a one-to-many relationship.
     * 
     * Missing objects will be deleted.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string $foreignColumn Property on foreign object that relates to this object id
     */
    public function saveMany(array $objects, $className, $foreignObjectGetter = null, $foreignColumn = null)
    {
        if(empty($objects)) {
            return;
        }

        if(!$foreignObjectGetter) {
            $foreignObjectGetter = 'get' . $this->inflector->methodNameFromClass($className, true);
        }

        if(!$foreignColumn) {
            $foreignColumn = $this->inflector->idColumnFromClass(get_class(reset($objects)));
        }
        $objectIdSetter = 'set' . $foreignColumn;

        $existingForeignIdsByObjectId = $this->getExistingForeignIds($objects, $className, $foreignColumn);

        $foreignObjectsToSave = [];
        $foreignIdsToDelete = [];
        foreach($objects as $object) {
            if(!method_exists($object, $foreignObjectGetter)) {
                throw new MethodNotImplementedException("$foreignObjectGetter must be defined on {$object->getClassName()} to save relationship");
            }

            $existingForeignIds = [];
            if(isset($existingForeignIdsByObjectId[$object->getId()])) {
                $existingForeignIds = $existingForeignIdsByObjectId[$object->getId()];
            }
            /** @var DataObjectInterface[] $foreignObjects */
            $foreignObjects = $object->{$foreignObjectGetter}();
            if(!empty($foreignObjects)) {
                if(!is_array($foreignObjects)) {
                    throw new MethodNotImplementedException("$foreignObjectGetter on {$object->getClassName()} must return an array to save relationship");
                }

                foreach($foreignObjects as $foreignObject) {
                    if(!method_exists($foreignObject, $objectIdSetter)) {
                        throw new MethodNotImplementedException("$objectIdSetter must be defined on {$foreignObject->getClassName()} to save relationship");
                    }

                    $foreignObject->{$objectIdSetter}($object->getId());
                    $foreignObjectsToSave[] = $foreignObject;
                    if($foreignObject->getId()) {
                        unset($existingForeignIds[$foreignObject->getId()]);
                    }
                }
            }
            
            foreach($existingForeignIds as $id => $true) {
                $foreignIdsToDelete[] = $id;
            }
        }
        
        $this->objectMapper->saveAll($foreignObjectsToSave);
        
        $foreignObjectsToDelete = [];
        foreach($foreignIdsToDelete as $id) {
            $foreignObject = $this->objectMapper->create($className);
            $foreignObject->setId($id);
            $foreignObjectsToDelete[] = $foreignObject;
        }
        $this->objectMapper->deleteAll($foreignObjectsToDelete);
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     *
     * This method does not insert or update the foreign objects.
     * Missing foreign objects will be removed from the link table.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     */
    public function saveManyToManyLinks(array $objects, $className, $linkTable, $foreignObjectGetter = null, $idColumn = null, $foreignIdColumn = null)
    {
        if(empty($objects)) {
            return;
        }

        if(!$foreignObjectGetter) {
            $foreignObjectGetter = 'get' . $this->inflector->methodNameFromClass($className, true);
        }

        if(!$idColumn) {
            $idColumn = $this->inflector->idColumnFromClass(get_class(reset($objects)));
        }

        if(!$foreignIdColumn) {
            $foreignIdColumn = $this->inflector->idColumnFromClass($className);
        }

        $queryHelper = $this->objectMapper->getQueryHelper();
        $queryHelper->buildDeleteQuery($linkTable, [$idColumn=>DataObject::getIds($objects)])
            ->execute();
        
        $linkData = [];
        foreach($objects as $object) {
            if(!method_exists($object, $foreignObjectGetter)) {
                throw new MethodNotImplementedException("$foreignObjectGetter must be defined on {$object->getClassName()} to save relationship");
            }

            /** @var DataObjectInterface[] $foreignObjects */
            $foreignObjects = $object->{$foreignObjectGetter}();
            if(!empty($foreignObjects)) {
                if(!is_array($foreignObjects)) {
                    throw new MethodNotImplementedException("$foreignObjectGetter on {$object->getClassName()} must return an array to save relationship");
                }

                foreach($foreignObjects as $foreignObject) {
                    $linkData[] = [$idColumn=>$object->getId(), $foreignIdColumn=>$foreignObject->getId()];
                }
            }
        }
        $queryHelper->massInsert($linkTable, $linkData);
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     *
     * This method inserts / updates the foreign objects.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     */
    public function saveManyToMany(array $objects, $className, $linkTable, $foreignObjectGetter = null, $idColumn = null, $foreignIdColumn = null)
    {
        if(empty($objects)) {
            return;
        }

        if(!$foreignObjectGetter) {
            $foreignObjectGetter = 'get' . $this->inflector->methodNameFromClass($className, true);
        }

        $foreignObjectsToSave = [];
        foreach($objects as $object) {
            if(!method_exists($object, $foreignObjectGetter)) {
                throw new MethodNotImplementedException("$foreignObjectGetter must be defined on {$object->getClassName()} to save relationship");
            }

            /** @var DataObjectInterface[] $foreignObjects */
            $foreignObjects = $object->{$foreignObjectGetter}();
            if(!empty($foreignObjects)) {
                if(!is_array($foreignObjects)) {
                    throw new MethodNotImplementedException("$foreignObjectGetter on {$object->getClassName()} must return an array to save relationship");
                }

                foreach($foreignObjects as $foreignObject) {
                    $foreignObjectsToSave[] = $foreignObject;
                }
            }
        }
        $this->objectMapper->saveAll($foreignObjectsToSave);
        
        $this->saveManyToManyLinks($objects, $className, $linkTable, $foreignObjectGetter, $idColumn, $foreignIdColumn);
    }

    /**
     * @param array $objects
     * @param $className
     * @param $foreignColumn
     * @return array
     */
    protected function getExistingForeignIds(array $objects, $className, $foreignColumn)
    {
        $objectIds = DataObject::getIds($objects);
        $queryHelper = $this->objectMapper->getQueryHelper();
        $foreignTable = $className::getTableName();
        $foreignColumns = $queryHelper->getDbColumns($foreignTable);

        $criteria = [$foreignColumn => $objectIds];
        if (isset($foreignColumns['isDeleted'])) {
            $criteria['isDeleted'] = 0;
        }
        $qb = $queryHelper->buildSelectQuery($foreignTable, ['id', $queryHelper->getConnection()->quoteIdentifier($foreignColumn)], $criteria);
        $existingForeignObjectIds = $qb->execute()->fetchAll(\PDO::FETCH_NUM);

        $existingForeignObjectsIdsByObjectId = [];
        foreach ($existingForeignObjectIds as $row) {
            list($foreignId, $objectId) = $row;
            if (!isset($existingForeignObjectsIdsByObjectId[$objectId])) {
                $existingForeignObjectsIdsByObjectId[$objectId] = [$foreignId=>true];
            } else {
                $existingForeignObjectsIdsByObjectId[$objectId][$foreignId] = true;
            }
        }
        return $existingForeignObjectsIdsByObjectId;
    }
}