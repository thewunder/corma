<?php
namespace Corma\DataObject\Identifier;

use Corma\Exception\MethodNotImplementedException;
use Corma\Util\Inflector;

abstract class BaseIdentifier implements ObjectIdentifierInterface
{
    public function __construct(protected Inflector $inflector)
    {
    }

    /**
     * @param object $object
     * @return string|null
     */
    public function getId(object $object): ?string
    {
        $getter = $this->inflector->getterFromColumn($this->getIdColumn($object));
        if (!method_exists($object, $getter)) {
            throw new MethodNotImplementedException("$getter must be implemented on ".$object::class);
        }
        return $object->$getter();
    }

    /**
     * @param array $objects
     * @return string[]
     */
    public function getIds(array $objects): array
    {
        if (empty($objects)) {
            return [];
        }

        $object = reset($objects);
        $getter = $this->inflector->getterFromColumn($this->getIdColumn($object));
        if (!method_exists($object, $getter)) {
            throw new MethodNotImplementedException("$getter must be implemented on ".$object::class);
        }

        $ids = [];
        foreach ($objects as $object) {
            $ids[] = $object->$getter();
        }

        return $ids;
    }

    /**
     * @param object $object
     * @param string $id
     * @return object
     */
    public function setId(object $object, $id): object
    {
        $setter = $this->inflector->setterFromColumn($this->getIdColumn($object));
        if (method_exists($object, $setter)) {
            $object->$setter($id);
            return $object;
        }

        throw new MethodNotImplementedException("$setter must be implemented on ".$object::class);
    }

    /**
     * @param string|object $objectOrClass
     * @return string Database column name containing the identifier for the object
     */
    public function getIdColumn(object|string $objectOrClass): string
    {
        return 'id';
    }
}
