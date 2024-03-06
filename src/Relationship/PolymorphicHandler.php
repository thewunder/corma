<?php

namespace Corma\Relationship;

use Corma\Exception\MethodNotImplementedException;

final class PolymorphicHandler extends BaseRelationshipHandler
{
    public static function getRelationshipClass(): string
    {
        return Polymorphic::class;
    }

    public function load(array $objects, Relationship $relationship): array
    {
        $getterBase = $this->inflector->getterFromColumn($relationship->getProperty());
        $classGetter = $getterBase . 'Class';
        $idGetter = $getterBase . 'Id';
        $setter = $this->inflector->setterFromColumn($relationship->getProperty());
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
        $namespace = $relationship->getClassName();

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
        $idSetter = $this->inflector->setterFromColumn($property) . 'Id';
        $classSetter = $this->inflector->setterFromColumn($property) . 'Class';
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
}
