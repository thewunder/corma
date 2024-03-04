<?php

namespace Corma\Relationship;

use Corma\Exception\MethodNotImplementedException;
use Corma\ObjectMapper;
use Corma\Util\Inflector;

final class OneToOneHandler implements RelationshipHandler
{
    private readonly Inflector $inflector;
    public function __construct(private readonly ObjectMapper $objectMapper)
    {
        $this->inflector = $this->objectMapper->getInflector();
    }
    public static function getRelationshipClass(): string
    {
        return OneToOne::class;
    }

    public function load(array $objects, RelationshipType|OneToOne $relationship): array
    {
        if (empty($objects)) {
            return [];
        }

        $idToForeignId = [];
        $className = $relationship->getClassName();
        $foreignIdColumn = $relationship->getForeignIdColumn();
        $foreignIdColumn ??= $this->inflector->idColumnFromProperty($relationship->getProperty());

        $om = $this->objectMapper->getObjectManager($objects);
        $fom = $this->objectMapper->getObjectManager($className);

        $getter = $this->inflector->getterFromColumn($foreignIdColumn);
        foreach ($objects as $i => $object) {
            if (method_exists($object, $getter)) {
                $id = $om->getId($object);
                if (!$id) {
                    $id = $i;
                }

                $foreignId = $object->$getter();
                if ($foreignId) {
                    $idToForeignId[$id] = $foreignId;
                }
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

        $setter ??= 'set' . $this->inflector->methodNameFromColumn($foreignIdColumn);
        foreach ($objects as $i => $object) {
            if (method_exists($object, $setter)) {
                $id = $om->getId($object);
                if (!$id) {
                    $id = $i;
                }

                if (isset($idToForeignId[$id]) && isset($foreignObjectsById[$idToForeignId[$id]])) {
                    $object->$setter($foreignObjectsById[$idToForeignId[$id]]);
                }
            } else {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$setter must be defined on {$shortClass} to load one-to-one relationship at $className");
            }
        }
        return $foreignObjectsById;
    }

    public function save(array $objects, RelationshipType|OneToOne $relationship): void
    {
        if (empty($objects)) {
            return;
        }

        $om = $this->objectMapper->getObjectManager($objects);

        $foreignIdColumn = $relationship->getForeignIdColumn();

        $foreignIdColumn ??= $this->inflector->idColumnFromProperty($relationship->getProperty());
        $getter ??= $this->inflector->getterFromColumn($relationship->getProperty());

        /** @var object[] $foreignObjectsByObjectId */
        $foreignObjectsByObjectId = [];
        foreach ($objects as $object) {
            if (!method_exists($object, $getter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$getter must be defined on {$shortClass} to save relationship");
            }

            $objectIdSetter = 'set' . $this->inflector->idColumnFromClass($object::class);
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

        $idSetter = $this->inflector->setterFromColumn($foreignIdColumn);
        $idGetter = $this->inflector->getterFromColumn($foreignIdColumn);
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
}
