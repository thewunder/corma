<?php
namespace Corma\DataObject\Identifier;

use Corma\Exception\MethodNotImplementedException;
use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;

abstract class BaseIndentifier implements ObjectIdentifierInterface
{
    /**
     * @var Inflector
     */
    protected $inflector;

    public function __construct(Inflector $inflector)
    {
        $this->inflector = $inflector;
    }

    /**
     * @param object $object
     * @return string
     */
    public function getId($object): ?string
    {
        $getter = $this->inflector->getterFromColumn($this->getIdColumn($object));
        if (!method_exists($object, $getter)) {
            throw new MethodNotImplementedException("$getter must be implemented on ".get_class($object));
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
            throw new MethodNotImplementedException("$getter must be implemented on ".get_class($object));
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
    public function setId($object, $id)
    {
        $setter = $this->inflector->setterFromColumn($this->getIdColumn($object));
        if (method_exists($object, $setter)) {
            $object->$setter($id);
            return $object;
        }

        throw new MethodNotImplementedException("$setter must be implemented on ".get_class($object));
    }

    /**
     * @param string|object $objectOrClass
     * @return string Database column name containing the identifier for the object
     */
    public function getIdColumn($objectOrClass): string
    {
        return 'id';
    }
}
