<?php

namespace Corma\Relationship;

use Corma\Exception\InvalidArgumentException;
use Corma\Exception\MethodNotImplementedException;
use Doctrine\DBAL\Query\QueryBuilder;

final class PolymorphicHandler extends BaseRelationshipHandler
{
    public static function getRelationshipClass(): string
    {
        return Polymorphic::class;
    }

    public function load(array $objects, Relationship $relationship): array
    {
        $property = $relationship->getProperty();
        $idColumn = $this->inflector->idColumnFromProperty($property);
        $idGetter = $this->inflector->getterFromColumn($idColumn);
        $classColumn = $this->classColumnFromProperty($property);
        $classGetter = $this->inflector->getterFromColumn($classColumn);
        $setter = $this->inflector->setterFromColumn($property);
        $byClass = [];
        $byId = [];
        foreach ($objects as $object) {
            if (!method_exists($object, $classGetter) || !method_exists($object, $idGetter) || !method_exists($object, $setter)) {
                throw new MethodNotImplementedException($object::class. " must implement $classGetter, $idGetter, and $setter");
            }

            $id = $object->{$idGetter}();
            $class = $object->{$classGetter}();
            if ($id && $class) {
                $byClass[$class][$object->getId()] = $id;
                $byId[$object->getId()] = $object;
            }
        }

        $foreignObjectsByClass = [];
        $namespace = $relationship->getForeignClass();

        foreach ($byClass as $objectClass => $loadByIds) {
            $fullClass = $namespace . '\\' . $objectClass;
            $foreignObjects = $this->objectMapper->findByIds($fullClass, $loadByIds);
            $om = $this->objectMapper->getObjectManager($fullClass);
            $foreignObjectsById = [];
            foreach ($foreignObjects as $foreignObject) {
                $foreignObjectsById[$om->getId($foreignObject)] = $foreignObject;
            }
            $foreignObjectsByClass[$objectClass] = $foreignObjectsById;

            foreach ($loadByIds as $objectId => $foreignObjectId) {
                if (isset($foreignObjectsById[$foreignObjectId])) {
                    $foreignObject = $foreignObjectsById[$foreignObjectId];
                    $byId[$objectId]->{$setter}($foreignObject);
                }
            }
        }

        return $foreignObjectsByClass;
    }

    public function save(array $objects, Relationship $relationship): void
    {
        $property = $relationship->getProperty();
        $getter = $this->inflector->getterFromColumn($property);
        $idSetter = $this->inflector->setterFromColumn($this->inflector->idColumnFromProperty($property));
        $classSetter = $this->inflector->setterFromColumn($this->classColumnFromProperty($property));
        $foreignObjects = [];
        $objectsById = [];
        $om = $this->objectMapper->getObjectManager($objects);
        foreach ($objects as $object) {
            if (!method_exists($object, $getter) || !method_exists($object, $idSetter) || !method_exists($object, $classSetter)) {
                throw new MethodNotImplementedException($object::class. " must implement $getter, $idSetter, and $classSetter");
            }

            $id = $om->getId($object);

            $foreignObject = $object->{$getter}();
            if ($foreignObject) {
                $id ??= spl_object_hash($object);
                $foreignObjects[$id] = $foreignObject;
                $objectsById[$id] = $object;
            }
        }

        $this->objectMapper->unitOfWork()->saveAll($foreignObjects)->flush();

        /**
         * @var string|int $objectId
         * @var object $foreignObject
         * */
        foreach ($foreignObjects as $objectId => $foreignObject) {
            $fom = $this->objectMapper->getObjectManager($foreignObject);
            $foreignId = $fom->getId($foreignObject);
            $object = $objectsById[$objectId];
            $object->{$idSetter}($foreignId);
            $object->{$classSetter}((new \ReflectionClass($foreignObject))->getShortName());
        }
    }

    public function join(QueryBuilder $qb, string $fromAlias, Polymorphic|Relationship $relationship, JoinType $type = JoinType::INNER, mixed $additional = null): string
    {
        $foreignClass = $additional;
        if (empty($foreignClass) || !class_exists($foreignClass)) {
            throw new InvalidArgumentException('Must provide the class name of the class to join to');
        }

        $db = $qb->getConnection();

        $fom = $this->objectMapper->getObjectManager($foreignClass);
        $class = (new \ReflectionClass($foreignClass))->getShortName();
        $foreignTable = $fom->getTable();
        $property = $relationship->getProperty();
        $aliasPrefix = $this->inflector->aliasFromProperty($property);
        $alias = $aliasPrefix . '_' . $this->inflector->aliasFromProperty(lcfirst($class));
        $idColumn = $db->quoteIdentifier($this->inflector->idColumnFromProperty($property));
        $classColumn = $db->quoteIdentifier($this->classColumnFromProperty($property));
        $foreignIdColumn = $db->quoteIdentifier($fom->getIdColumn());
        $functionName = $type->value . 'Join';
        $on = "$fromAlias.$idColumn = $alias.$foreignIdColumn AND $fromAlias.$classColumn = '$class'";
        $qb->$functionName($fromAlias, $foreignTable, $alias, $on);
        return $alias;
    }

    private function classColumnFromProperty(string $property): string
    {
        return $property . 'Class';
    }
}
