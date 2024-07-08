<?php

namespace Corma\Relationship;

use Corma\Exception\MethodNotImplementedException;
use Corma\DBAL\Query\QueryBuilder;

final class OneToManyHandler extends BaseRelationshipHandler
{
    public static function getRelationshipClass(): string
    {
        return OneToMany::class;
    }

    public function load(array $objects, OneToMany|Relationship $relationship): array
    {
        if (empty($objects)) {
            return [];
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $className = $relationship->getForeignClass();
        $fom = $this->objectMapper->getObjectManager($className);
        $ids = $om->getIds($objects);

        $foreignColumn = $this->foreignIdColumn($relationship);

        $foreignObjects = $this->objectMapper->findBy($className, [$foreignColumn => $ids]);
        $foreignObjectsById = [];
        $getter = $relationship->getForeignObjectGetter();
        $getter ??= 'get' . ucfirst($foreignColumn);
        foreach ($foreignObjects as $foreignObject) {
            if (method_exists($foreignObject, $getter)) {
                $id = $foreignObject->$getter();
                $foreignObjectsById[$id][] = $foreignObject;
            } else {
                $foreignShortClass = $this->inflector->getShortClass($foreignObject);
                throw new MethodNotImplementedException("$getter must be defined on $className to load one-to-many relationship with {$foreignShortClass}");
            }
        }

        $setter ??= 'set' . $this->inflector->methodNameFromColumn($relationship->getProperty(), true);
        foreach ($objects as $object) {
            if (method_exists($object, $setter)) {
                $id = $om->getId($object);
                if (isset($foreignObjectsById[$id])) {
                    $object->$setter($foreignObjectsById[$id]);
                } else {
                    $object->$setter([]);
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

    public function save(array $objects, OneToMany|Relationship $relationship): void
    {
        if (empty($objects)) {
            return;
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $className = $relationship->getForeignClass();
        $property = $relationship->getProperty();
        $fom = $this->objectMapper->getObjectManager($className);

        $foreignObjectGetter = $relationship->getForeignObjectGetter();
        $foreignObjectGetter ??= 'get' . $this->inflector->methodNameFromColumn($property, true);


        $foreignColumn = $this->foreignIdColumn($relationship);
        $objectIdSetter = 'set' . ucfirst($foreignColumn);

        $deleteMissing = $relationship->deleteMissing();
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
     * Retrieve foreign ids for a one-to-many relationship
     *
     * @param object[] $objects
     * @return array objectId => map of foreign ids
     */
    private function getExistingForeignIds(array $objects, string $className, string $foreignColumn): array
    {
        $om = $this->objectMapper->getObjectManager($objects);
        $objectIds = $om->getIds($objects);
        $queryHelper = $this->objectMapper->getQueryHelper();

        $fom = $this->objectMapper->getObjectManager($className);
        $foreignTable = $fom->getTable();
        $idColumn = $fom->getIdColumn();

        $connection = $queryHelper->getConnection();
        $qb = $queryHelper->buildSelectQuery($foreignTable, [$connection->quoteIdentifier($idColumn), $connection->quoteIdentifier($foreignColumn)], [$foreignColumn => $objectIds]);
        $existingForeignObjectIds = $qb->executeQuery()->fetchAllNumeric();

        $existingForeignObjectsIdsByObjectId = [];
        foreach ($existingForeignObjectIds as $row) {
            [$foreignId, $objectId] = $row;
            if (!isset($existingForeignObjectsIdsByObjectId[$objectId])) {
                $existingForeignObjectsIdsByObjectId[$objectId] = [$foreignId=>true];
            } else {
                $existingForeignObjectsIdsByObjectId[$objectId][$foreignId] = true;
            }
        }
        return $existingForeignObjectsIdsByObjectId;
    }

    public function join(QueryBuilder $qb, string $fromAlias, OneToMany|Relationship $relationship, JoinType $type = JoinType::INNER, mixed $additional = null): string
    {
        $om = $this->objectMapper->getObjectManager($relationship->getClass());
        $conn = $qb->getConnection();
        $foreignOM = $this->objectMapper->getObjectManager($relationship->getForeignClass());
        $foreignTable = $conn->quoteIdentifier($foreignOM->getTable());
        $idColumn = $conn->quoteIdentifier($om->getIdColumn());
        $foreignIdColumn = $conn->quoteIdentifier($this->foreignIdColumn($relationship));
        $alias = $this->inflector->aliasFromProperty($relationship->getProperty());
        $functionName = $type->value . 'Join';
        $qb->$functionName($fromAlias, $foreignTable, $alias, "$fromAlias.$idColumn = $alias.$foreignIdColumn");
        return $alias;
    }

    private function foreignIdColumn(OneToMany $relationship): string
    {
        $foreignColumn = $relationship->getForeignColumn();
        $foreignColumn ??= $this->inflector->idColumnFromClass($relationship->getClass());
        return $foreignColumn;
    }
}
