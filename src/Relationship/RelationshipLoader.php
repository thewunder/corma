<?php
namespace Corma\Relationship;

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
     * @param object[] $objects Data objects of the same class
     * @param string $className Class name of foreign object to load
     * @param string $foreignIdColumn Property on this object that relates to the foreign tables id
     * @param string $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadOne(array $objects, string $className, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        if (empty($objects)) {
            return [];
        }

        $idToForeignId = [];
        $foreignIdColumn = $foreignIdColumn ?? $this->inflector->idColumnFromClass($className);
        $foreignIdColumn = ucfirst($foreignIdColumn);

        $om = $this->objectMapper->getObjectManager($objects);
        $fom = $this->objectMapper->getObjectManager($className);

        $getter = 'get' . $foreignIdColumn;
        foreach ($objects as $i => $object) {
            if (method_exists($object, $getter)) {
                $id = $om->getId($object);
                if (!$id) {
                    $id = $i;
                }

                $idToForeignId[$id] = $object->$getter();
            } else {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$getter must be defined on {$shortClass} to load one-to-one relationship with $className");
            }
        }

        $foreignObjects = $this->objectMapper->findByIds($className, array_unique(array_values($idToForeignId)));
        $foreignObjectsById = [];
        foreach ($foreignObjects as $foreignObject) {
            $foreignObjectsById[$fom->getId($foreignObject)] = $foreignObject;
        }
        unset($foreignObjects);

        $setter = $setter ?? 'set' . $this->inflector->methodNameFromColumn($foreignIdColumn);
        foreach ($objects as $i => $object) {
            if (method_exists($object, $setter)) {
                $id = $om->getId($object);
                if (!$id) {
                    $id = $i;
                }

                if (isset($foreignObjectsById[$idToForeignId[$id]])) {
                    $object->$setter($foreignObjectsById[$idToForeignId[$id]]);
                }
            } else {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$setter must be defined on {$shortClass} to load one-to-one relationship at $className");
            }
        }
        return $foreignObjectsById;
    }

    /**
     * Loads a foreign relationship where a column on another object references the id for the supplied objects.
     *
     * Used to load the "many" side of a one-to-many relationship.
     *
     * @param object[] $objects Data objects of the same class
     * @param string $className Class name of foreign objects to load
     * @param string $foreignColumn Property on foreign object that relates to this object id
     * @param string $setter Name of setter method on objects
     * @return array|\object[] Loaded objects keyed by id
     */
    public function loadMany(array $objects, string $className, ?string $foreignColumn = null, ?string $setter = null): array
    {
        if (empty($objects)) {
            return [];
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $fom = $this->objectMapper->getObjectManager($className);
        $ids = $om->getIds($objects);

        $foreignColumn = $foreignColumn ?? $this->inflector->idColumnFromClass(get_class(reset($objects)));

        $foreignObjects = $this->objectMapper->findBy($className, [$foreignColumn => $ids]);
        $foreignObjectsById = [];
        $getter = 'get' . ucfirst($foreignColumn);
        foreach ($foreignObjects as $foreignObject) {
            if (method_exists($foreignObject, $getter)) {
                $id = $foreignObject->$getter();
                $foreignObjectsById[$id][] = $foreignObject;
            } else {
                $foreignShortClass = $this->inflector->getShortClass($foreignObject);
                throw new MethodNotImplementedException("$getter must be defined on $className to load one-to-many relationship with {$foreignShortClass}");
            }
        }

        $setter = $setter ?? 'set' . $this->inflector->methodNameFromClass($className, true);
        foreach ($objects as $object) {
            if (method_exists($object, $setter)) {
                $id = $om->getId($object);
                if (isset($foreignObjectsById[$id])) {
                    $object->$setter($foreignObjectsById[$id]);
                }
            } else {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$setter must be defined on {$shortClass} to load one-to-many relationship with $className");
            }
        }

        $flattenedForeignObjects = [];
        foreach ($foreignObjectsById as $array) {
            /** @var object $object */
            foreach ($array as $object) {
                $flattenedForeignObjects[$fom->getId($object)] = $object;
            }
        }
        return $flattenedForeignObjects;
    }

    /**
     * Loads objects of the foreign class onto the supplied objects linked by a link table containing the id's of both objects
     *
     * @param object[] $objects Data objects of the same class
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     * @param string $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadManyToMany(array $objects, string $className, string $linkTable, ?string $idColumn = null, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        if (empty($objects)) {
            return [];
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $fom = $this->objectMapper->getObjectManager($className);

        $idColumn = $idColumn ?? $this->inflector->idColumnFromClass(get_class(reset($objects)));
        $foreignIdColumn = $foreignIdColumn ?? $this->inflector->idColumnFromClass($className);

        $ids = $om->getIds($objects);
        $queryHelper = $this->objectMapper->getQueryHelper();
        $db = $queryHelper->getConnection();
        $qb = $queryHelper->buildSelectQuery($linkTable, [$db->quoteIdentifier($idColumn).' AS id', $db->quoteIdentifier($foreignIdColumn).' AS '. $db->quoteIdentifier('foreignId')], [$idColumn=>$ids]);
        $foreignIdsById = [];
        $foreignIds = [];
        $linkRows = $qb->execute();
        $linkRows->setFetchMode(\PDO::FETCH_OBJ);
        foreach ($linkRows as $linkRow) {
            $foreignIdsById[$linkRow->id][] = $linkRow->foreignId;
            $foreignIds[$linkRow->foreignId] = true;
        }

        $foreignObjects = $this->objectMapper->findByIds($className, array_keys($foreignIds));
        unset($foreignIds);

        $foreignObjectsById = [];
        foreach ($foreignObjects as $foreignObject) {
            $foreignObjectsById[$fom->getId($foreignObject)] = $foreignObject;
        }
        unset($foreignObjects);

        $setter =  $setter ?? 'set' . $this->inflector->methodNameFromColumn($foreignIdColumn, true);
        foreach ($objects as $object) {
            if (method_exists($object, $setter)) {
                $foreignObjects = [];
                $id = $om->getId($object);
                if (isset($foreignIdsById[$id])) {
                    $foreignIds = $foreignIdsById[$id];
                    foreach ($foreignIds as $foreignId) {
                        if (isset($foreignObjectsById[$foreignId])) {
                            $foreignObjects[] = $foreignObjectsById[$foreignId];
                        }
                    }
                }

                $object->$setter($foreignObjects);
            } else {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$setter must be defined on {$shortClass} to load many-to-many relationship with $className");
            }
        }
        return $foreignObjectsById;
    }
}
