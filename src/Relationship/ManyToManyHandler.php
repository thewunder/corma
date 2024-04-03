<?php

namespace Corma\Relationship;

use Corma\Exception\MethodNotImplementedException;
use Doctrine\DBAL\Query\QueryBuilder;

final class ManyToManyHandler extends BaseRelationshipHandler
{
    public static function getRelationshipClass(): string
    {
        return ManyToMany::class;
    }

    public function load(array $objects, ManyToMany|Relationship $relationship): array
    {
        if (empty($objects)) {
            return [];
        }

        $om = $this->objectMapper->getObjectManager($objects);
        $className = $relationship->getForeignClass();
        $fom = $this->objectMapper->getObjectManager($className);

        $idColumn = $this->idColumn($relationship);
        $foreignIdColumn = $this->foreignIdColumn($relationship);

        $ids = $om->getIds($objects);
        $queryHelper = $this->objectMapper->getQueryHelper();
        $db = $queryHelper->getConnection();
        $linkTable = $relationship->getLinkTable();
        $qb = $queryHelper->buildSelectQuery($linkTable, [$db->quoteIdentifier($idColumn).' AS id', $db->quoteIdentifier($foreignIdColumn).' AS '. $db->quoteIdentifier('foreignId')], [$idColumn=>$ids]);
        $foreignIdsById = [];
        $foreignIds = [];
        $linkRows = $qb->executeQuery();
        while ($linkRow = $linkRows->fetchAssociative()) {
            $foreignIdsById[$linkRow['id']][] = $linkRow['foreignId'];
            $foreignIds[$linkRow['foreignId']] = true;
        }

        $foreignObjects = $this->objectMapper->findByIds($className, array_keys($foreignIds));
        unset($foreignIds);

        $foreignObjectsById = [];
        foreach ($foreignObjects as $foreignObject) {
            $foreignObjectsById[$fom->getId($foreignObject)] = $foreignObject;
        }
        unset($foreignObjects);

        $setter ??= 'set' . $this->inflector->methodNameFromColumn($relationship->getProperty(), true);
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
                throw new MethodNotImplementedException("$setter must be defined on $shortClass to load many-to-many relationship with $className");
            }
        }
        return $foreignObjectsById;
    }

    public function save(array $objects, ManyToMany|Relationship $relationship): void
    {
        if (empty($objects)) {
            return;
        }

        if ($relationship->isShallow()) {
            $this->saveManyToManyLinks($objects, $relationship);
            return;
        }

        $foreignObjectGetter = 'get' . $this->inflector->methodNameFromColumn($relationship->getProperty(), true);

        $foreignObjectsToSave = [];
        foreach ($objects as $object) {
            if (!method_exists($object, $foreignObjectGetter)) {
                $shortClass = $this->inflector->getShortClass($object);
                throw new MethodNotImplementedException("$foreignObjectGetter must be defined on $shortClass to save relationship");
            }

            /** @var object[] $foreignObjects */
            $foreignObjects = $object->{$foreignObjectGetter}();
            if (!empty($foreignObjects)) {
                if (!is_array($foreignObjects)) {
                    $shortClass = $this->inflector->getShortClass($object);
                    throw new MethodNotImplementedException("$foreignObjectGetter on $shortClass must return an array to save relationship");
                }

                foreach ($foreignObjects as $foreignObject) {
                    $foreignObjectsToSave[] = $foreignObject;
                }
            }
        }

        $this->objectMapper->unitOfWork()->executeTransaction(
            function () use ($foreignObjectsToSave, $objects, $relationship) {
                $this->objectMapper->saveAll($foreignObjectsToSave);
                $this->saveManyToManyLinks($objects, $relationship);
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
     */
    public function saveManyToManyLinks(array $objects, ManyToMany $relationship): void
    {
        $foreignObjectGetter = 'get' . $this->inflector->methodNameFromColumn($relationship->getProperty(), true);

        $idColumn = $this->idColumn($relationship);
        $foreignIdColumn = $this->foreignIdColumn($relationship);

        $om = $this->objectMapper->getObjectManager($objects);
        $className = $relationship->getForeignClass();
        $fom = $this->objectMapper->getObjectManager($className);
        $linkTable = $relationship->getLinkTable();

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
            $queryHelper->buildDeleteQuery($linkTable, [$idColumn=>$om->getIds($objects)])->executeStatement();
            $queryHelper->massInsert($linkTable, $linkData);
        });
    }

    public function join(QueryBuilder $qb, string $fromAlias, ManyToMany|Relationship $relationship, JoinType $type = JoinType::INNER, mixed $additional = null): string
    {
        $om = $this->objectMapper->getObjectManager($relationship->getClass());
        $foreignOM = $this->objectMapper->getObjectManager($relationship->getForeignClass());
        $conn = $qb->getConnection();
        $foreignTable = $conn->quoteIdentifier($foreignOM->getTable());
        $idColumn = $conn->quoteIdentifier($om->getIdColumn());
        $linkIdColumn = $conn->quoteIdentifier($this->idColumn($relationship));
        $linkForeignIdColumn = $conn->quoteIdentifier($this->foreignIdColumn($relationship));
        $foreignIdColumn = $foreignOM->getIdColumn();
        $alias = $this->inflector->aliasFromProperty($relationship->getProperty());
        $linkAlias = $alias . '_link';
        $functionName = $type->value . 'Join';
        $qb->$functionName($fromAlias, $relationship->getLinkTable(), $linkAlias, "$fromAlias.$idColumn = $linkAlias.$linkIdColumn");
        $qb->$functionName($linkAlias, $foreignTable, $alias, "$linkAlias.$linkForeignIdColumn = $alias.$foreignIdColumn");
        return $alias;
    }

    private function foreignIdColumn(ManyToMany $relationship): string
    {
        $foreignIdColumn = $relationship->getForeignIdColumn();
        $foreignIdColumn ??= $this->inflector->idColumnFromClass($relationship->getForeignClass());
        return $foreignIdColumn;
    }

    public function idColumn(ManyToMany $relationship): string
    {
        $idColumn = $relationship->getIdColumn();
        $idColumn ??= $this->inflector->idColumnFromClass($relationship->getClass());
        return $idColumn;
    }
}
