<?php
namespace Corma\Relationship;

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
    private Inflector $inflector;

    public function __construct(private ObjectMapper $objectMapper)
    {
        $this->inflector = $objectMapper->getInflector();
    }

    /**
     * Saves a foreign relationship where a property on the supplied object references an id for another object.
     *
     * Can be used to save a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign object to load
     * @param string|null $foreignIdColumn Property on this object that relates to the foreign tables id
     * @param string|null $getter Name of getter method on objects
     */
    public function saveOne(array $objects, string $className, ?string $foreignIdColumn = null, ?string $getter = null): void
    {
        if (empty($objects)) {
            return;
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $foreignIdColumn = $foreignIdColumn ?? $this->inflector->idColumnFromClass($className);
        $getter = $getter ?? 'get' . $this->inflector->methodNameFromColumn($foreignIdColumn);

        /** @var object[] $foreignObjectsByObjectId */
        $foreignObjectsByObjectId = [];
        foreach ($objects as $object) {
            if (!method_exists($object, $getter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$getter must be defined on {$shortClass} to save relationship");
            }
            
            $objectIdSetter = 'set' . $this->inflector->idColumnFromClass(get_class($object));
            $foreignObject = $object->{$getter}();
            if ($foreignObject) {
                $id = $om->getId($object);
                $foreignObjectsByObjectId[$id] = $foreignObject;
                if (method_exists($foreignObject, $objectIdSetter)) { // for true one-to-one relationships
                    $foreignObject->{$objectIdSetter}($id);
                }
            }
        }

        if (empty($foreignObjectsByObjectId)) {
            return;
        }

        $this->objectMapper->saveAll($foreignObjectsByObjectId);
        
        $idSetter = 'set' . ucfirst($foreignIdColumn);
        $idGetter = 'get' . ucfirst($foreignIdColumn);
        $objectsToUpdate = [];

        $fom = $this->objectMapper->getObjectManager(reset($foreignObjectsByObjectId));

        foreach ($objects as $object) {
            if (!method_exists($object, $idSetter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$idSetter must be defined on {$shortClass} to save relationship");
            }
            if (!method_exists($object, $idGetter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$idGetter must be defined on {$shortClass} to save relationship");
            }

            $id = $om->getId($object);
            
            if (isset($foreignObjectsByObjectId[$id])) {
                $foreignObject = $foreignObjectsByObjectId[$id];
                if ($object->{$idGetter}() != $fom->getId($foreignObject)) {
                    $object->{$idSetter}($fom->getId($foreignObject));
                    $objectsToUpdate[] = $object;
                }
            }
        }

        $this->objectMapper->saveAll($objectsToUpdate, null);
    }

    /**
     * Saves a foreign relationship where a column on another object references the id for the supplied object.
     *
     * Used to save the "many" side of a one-to-many relationship.
     *
     * Missing objects will be deleted by default.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string|null $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string|null $foreignColumn Property on foreign object that relates to this object id
     * @param boolean $deleteMissing Set to false to leave objects alone if missing
     * @throws \Throwable
     */
    public function saveMany(array $objects, string $className, ?string $foreignObjectGetter = null, ?string $foreignColumn = null, bool $deleteMissing = true): void
    {
        if (empty($objects)) {
            return;
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $fom = $this->objectMapper->getObjectManager($className);

        if (!$foreignObjectGetter) {
            $foreignObjectGetter = 'get' . $this->inflector->methodNameFromClass($className, true);
        }

        if (!$foreignColumn) {
            $foreignColumn = $this->inflector->idColumnFromClass(get_class(reset($objects)));
        }
        $objectIdSetter = 'set' . ucfirst($foreignColumn);

        if ($deleteMissing) {
            $existingForeignIdsByObjectId = $this->getExistingForeignIds($objects, $className, $foreignColumn);
        }

        $foreignObjectsToSave = [];
        $foreignIdsToDelete = [];
        foreach ($objects as $i => $object) {

            if (!method_exists($object, $foreignObjectGetter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$foreignObjectGetter must be defined on {$shortClass} to save relationship");
            }

            $existingForeignIds = [];
            $id = $om->getId($object);
            if ($deleteMissing) {
                if (isset($existingForeignIdsByObjectId[$id])) {
                    $existingForeignIds = $existingForeignIdsByObjectId[$id];
                }
            }

            /** @var object[] $foreignObjects */
            $foreignObjects = $object->{$foreignObjectGetter}();
            if (!empty($foreignObjects)) {
                if (!is_array($foreignObjects)) {
                    $shortClass = $this->inflector->getShortClass($object);
                    throw new MethodNotImplementedException("$foreignObjectGetter on {$shortClass} must return an array to save relationship");
                }

                foreach ($foreignObjects as $j => $foreignObject) {
                    if (!method_exists($foreignObject, $objectIdSetter)) {
                        $foreignShortClass = $this->inflector->getShortClass($foreignObject);
                        throw new MethodNotImplementedException("$objectIdSetter must be defined on {$foreignShortClass} to save relationship");
                    }

                    $foreignObject->{$objectIdSetter}($id);
                    $foreignId = $fom->getId($foreignObject);

                    if ($foreignId) {
                        $foreignObjectsToSave[$foreignId] = $foreignObject;
                        unset($existingForeignIds[$foreignId], $foreignIdsToDelete[$foreignId]);
                    } else {
                        $foreignObjectsToSave["new_$i-$j"] = $foreignObject;
                    }
                }
            }

            $foreignIdsToDelete += $existingForeignIds;
        }

        $foreignIdsToDelete = array_diff_key($foreignIdsToDelete, $foreignObjectsToSave);

        $this->objectMapper->unitOfWork()->executeTransaction(
            function () use ($foreignObjectsToSave, $deleteMissing, $className, $foreignIdsToDelete) {
                $this->objectMapper->saveAll($foreignObjectsToSave);

                if ($deleteMissing && !empty($foreignIdsToDelete)) {
                    $foreignObjectsToDelete = $this->objectMapper->findByIds($className, array_keys($foreignIdsToDelete));
                    $this->objectMapper->deleteAll($foreignObjectsToDelete);
                }
            }
        );
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     *
     * This method does not insert or update the foreign objects.
     * Missing foreign objects will be removed from the link table.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string|null $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string|null $idColumn Column on link table = the id on this object
     * @param string|null $foreignIdColumn Column on link table = the id on the foreign object table
     * @throws \Throwable
     */
    public function saveManyToManyLinks(
        array $objects,
        string $className,
        string $linkTable,
        ?string $foreignObjectGetter = null,
        ?string $idColumn = null,
        ?string $foreignIdColumn = null
    ): void
    {
    
        if (empty($objects)) {
            return;
        }

        if (!$foreignObjectGetter) {
            $foreignObjectGetter = 'get' . $this->inflector->methodNameFromClass($className, true);
        }

        if (!$idColumn) {
            $idColumn = $this->inflector->idColumnFromClass(get_class(reset($objects)));
        }

        if (!$foreignIdColumn) {
            $foreignIdColumn = $this->inflector->idColumnFromClass($className);
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $fom = $this->objectMapper->getObjectManager($className);

        $linkData = [];
        foreach ($objects as $object) {
            if (!method_exists($object, $foreignObjectGetter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$foreignObjectGetter must be defined on {$shortClass} to save relationship");
            }

            /** @var object[] $foreignObjects */
            $foreignObjects = $object->{$foreignObjectGetter}();
            if (!empty($foreignObjects)) {
                if (!is_array($foreignObjects)) {
                    $shortClass = $this->inflector->getShortClass($object);
                    throw new MethodNotImplementedException("$foreignObjectGetter on {$shortClass} must return an array to save relationship");
                }

                $id = $om->getId($object);
                foreach ($foreignObjects as $foreignObject) {
                    $linkData[] = [$idColumn => $id, $foreignIdColumn =>$fom->getId($foreignObject)];
                }
            }
        }

        $this->objectMapper->unitOfWork()->executeTransaction(function () use ($linkTable, $idColumn, $objects, $linkData, $om) {
            $queryHelper = $this->objectMapper->getQueryHelper();
            $queryHelper->buildDeleteQuery($linkTable, [$idColumn=>$om->getIds($objects)])->execute();
            $queryHelper->massInsert($linkTable, $linkData);
        });
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     *
     * This method inserts / updates the foreign objects.
     * Missing foreign objects will be removed from the link table, but not deleted.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string|null $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string|null $idColumn Column on link table = the id on this object
     * @param string|null $foreignIdColumn Column on link table = the id on the foreign object table
     * @throws \Throwable
     */
    public function saveManyToMany(
        array $objects,
        string $className,
        string $linkTable,
        ?string $foreignObjectGetter = null,
        ?string $idColumn = null,
        ?string $foreignIdColumn = null
    ): void
    {
    
        if (empty($objects)) {
            return;
        }

        if (!$foreignObjectGetter) {
            $foreignObjectGetter = 'get' . $this->inflector->methodNameFromClass($className, true);
        }

        $foreignObjectsToSave = [];
        foreach ($objects as $object) {
            if (!method_exists($object, $foreignObjectGetter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$foreignObjectGetter must be defined on {$shortClass} to save relationship");
            }

            /** @var object[] $foreignObjects */
            $foreignObjects = $object->{$foreignObjectGetter}();
            if (!empty($foreignObjects)) {
                if (!is_array($foreignObjects)) {
                    $shortClass = $this->inflector->getShortClass($object);
                    throw new MethodNotImplementedException("$foreignObjectGetter on {$shortClass} must return an array to save relationship");
                }

                foreach ($foreignObjects as $foreignObject) {
                    $foreignObjectsToSave[] = $foreignObject;
                }
            }
        }

        $this->objectMapper->unitOfWork()->executeTransaction(
            function () use ($foreignObjectsToSave, $objects, $className, $linkTable, $foreignObjectGetter, $idColumn, $foreignIdColumn) {
                $this->objectMapper->saveAll($foreignObjectsToSave);
                $this->saveManyToManyLinks($objects, $className, $linkTable, $foreignObjectGetter, $idColumn, $foreignIdColumn);
            }
        );
    }

    /**
     * Retrieve foreign ids for a one-to-many relationship
     *
     * @param object[] $objects
     * @param string $className
     * @param string $foreignColumn
     * @return array objectId => map of foreign ids
     */
    protected function getExistingForeignIds(array $objects, string $className, string $foreignColumn): array
    {
        $om = $this->objectMapper->getObjectManager($objects);
        $objectIds = $om->getIds($objects);
        $queryHelper = $this->objectMapper->getQueryHelper();

        $fom = $this->objectMapper->getObjectManager($className);
        $foreignTable = $fom->getTable();
        $idColumn = $fom->getIdColumn();

        $connection = $queryHelper->getConnection();
        $qb = $queryHelper->buildSelectQuery($foreignTable, [$connection->quoteIdentifier($idColumn), $connection->quoteIdentifier($foreignColumn)], [$foreignColumn => $objectIds]);
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
