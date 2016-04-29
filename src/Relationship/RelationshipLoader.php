<?php
namespace Corma\Relationship;

use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;
use Corma\Exception\MethodNotImplementedException;
use Corma\ObjectMapper;
use Corma\Util\Inflector;

/**
 * Loads foreign relationships
 */
class RelationshipLoader
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
     * Loads a foreign relationship where a property on the supplied objects references an id for another object.
     *
     * Can be used to load a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign object to load
     * @param string $foreignIdColumn Property on this object that relates to the foreign tables id
     * @return DataObjectInterface[] Loaded objects keyed by id
     */
    public function loadOne(array $objects, $className, $foreignIdColumn)
    {
        if(empty($objects)) {
            return [];
        }

        $idToForeignId = [];
        $foreignIdColumn = ucfirst($foreignIdColumn);
        $getter = 'get' . $foreignIdColumn;
        foreach($objects as $object) {
            if(method_exists($object, $getter)) {
                $idToForeignId[$object->getId()] = $object->$getter();
            } else {
                throw new MethodNotImplementedException("$getter must be defined on {$object->getClassName()} to load one-to-one relationship with $className");
            }
        }

        $foreignObjects = $this->objectMapper->findByIds($className, array_unique(array_values($idToForeignId)));
        $foreignObjectsById = [];
        foreach($foreignObjects as $foreignObject) {
            $foreignObjectsById[$foreignObject->getId()] = $foreignObject;
        }
        unset($foreignObjects);

        $setter = 'set' . $this->inflector->methodNameFromColumn($foreignIdColumn);
        foreach($objects as $object) {
            if(method_exists($object, $setter)) {
                if(isset($foreignObjectsById[$idToForeignId[$object->getId()]])) {
                    $object->$setter($foreignObjectsById[$idToForeignId[$object->getId()]]);
                }
            } else {
                throw new MethodNotImplementedException("$setter must be defined on {$object->getClassName()} to load one-to-one relationship at $className");
            }
        }
        return $foreignObjectsById;
    }

    /**
     * Loads a foreign relationship where a column on another object references the id for the supplied objects.
     *
     * Used to load the "many" side of a one-to-many relationship.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $foreignColumn Property on foreign object that relates to this object id
     * @return DataObjectInterface[] Loaded objects keyed by id
     */
    public function loadMany(array $objects, $className, $foreignColumn)
    {
        if(empty($objects)) {
            return [];
        }

        $ids = DataObject::getIds($objects);

        $where = [$foreignColumn => $ids];
        $dbColumns = $this->objectMapper->getQueryHelper()->getDbColumns($className::getTableName());
        if(isset($dbColumns['isDeleted'])) {
            $where['isDeleted'] = 0;
        }
        $foreignObjects = $this->objectMapper->findBy($className, $where);
        $foreignObjectsById = [];
        $getter = 'get' . ucfirst($foreignColumn);
        foreach($foreignObjects as $foreignObject) {
            if(method_exists($foreignObject, $getter)) {
                $id = $foreignObject->$getter();
                $foreignObjectsById[$id][] = $foreignObject;
            } else {
                throw new MethodNotImplementedException("$getter must be defined on $className to load one-to-many relationship with {$foreignObject->getClassName()}");
            }
        }

        $setter = 'set' . $this->inflector->methodNameFromClass($className, true);
        foreach($objects as $object) {
            if(method_exists($object, $setter)) {
                if(isset($foreignObjectsById[$object->getId()])) {
                    $object->$setter($foreignObjectsById[$object->getId()]);
                }
            } else {
                throw new MethodNotImplementedException("$setter must be defined on {$object->getClassName()} to load one-to-many relationship with $className");
            }
        }

        $flattenedForeignObjects = [];
        foreach($foreignObjectsById as $array) {
            /** @var DataObjectInterface $object */
            foreach($array as $object) {
                $flattenedForeignObjects[$object->getId()] = $object;
            }
        }
        return $flattenedForeignObjects;
    }

    /**
     * Loads objects of the foreign class onto the supplied objects linked by a link table containing the id's of both objects
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
        if(empty($objects)) {
            return [];
        }

        $ids = DataObject::getIds($objects);
        $queryHelper = $this->objectMapper->getQueryHelper();
        $db = $queryHelper->getConnection();
        $qb = $queryHelper->buildSelectQuery($linkTable, [$db->quoteIdentifier($idColumn).' AS id', $db->quoteIdentifier($foreignIdColumn).' AS '. $db->quoteIdentifier('foreignId')], [$idColumn=>$ids]);
        $foreignIdsById = [];
        $foreignIds = [];
        $linkRows = $qb->execute();
        $linkRows->setFetchMode(\PDO::FETCH_OBJ);
        foreach($linkRows as $linkRow) {
            $foreignIdsById[$linkRow->id][] = $linkRow->foreignId;
            $foreignIds[$linkRow->foreignId] = true;
        }

        $foreignObjects = $this->objectMapper->findByIds($className, array_keys($foreignIds));
        unset($foreignIds);

        $foreignObjectsById = [];
        foreach($foreignObjects as $foreignObject) {
            $foreignObjectsById[$foreignObject->getId()] = $foreignObject;
        }
        unset($foreignObjects);

        $setter =  'set' . $this->inflector->methodNameFromColumn($foreignIdColumn, true);
        foreach($objects as $object) {
            if(method_exists($object, $setter)) {
                $foreignObjects = [];
                if(isset($foreignIdsById[$object->getId()])) {
                    $foreignIds = $foreignIdsById[$object->getId()];
                    foreach($foreignIds as $foreignId) {
                        $foreignObjects[] = $foreignObjectsById[$foreignId];
                    }
                }

                $object->$setter($foreignObjects);
            } else {
                throw new MethodNotImplementedException("$setter must be defined on {$object->getClassName()} to load many-to-many relationship with $className");
            }
        }
        return $foreignObjectsById;
    }
}
