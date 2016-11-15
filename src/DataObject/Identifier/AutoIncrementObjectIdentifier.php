<?php
namespace Corma\DataObject\Identifier;

use Corma\DataObject\TableConvention\TableConventionInterface;
use Corma\Exception\MethodNotImplementedException;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;

class AutoIncrementObjectIdentifier implements ObjectIdentifierInterface
{
    /**
     * @var Inflector
     */
    private $inflector;
    /**
     * @var QueryHelper
     */
    private $queryHelper;
    /**
     * @var TableConventionInterface
     */
    private $convention;
    /**
     * @var ReaderInterface
     */
    private $reader;

    public function __construct(Inflector $inflector, QueryHelper $queryHelper, TableConventionInterface $convention, ReaderInterface $reader)
    {
        $this->inflector = $inflector;
        $this->queryHelper = $queryHelper;
        $this->convention = $convention;
        $this->reader = $reader;
    }

    /**
     * @param object $object
     * @return string
     */
    public function getId($object)
    {
        $getter = $this->inflector->getterFromColumn($this->getIdColumn($object));
        if(!method_exists($object, $getter)) {
            throw new MethodNotImplementedException("$getter must be implemented on ".get_class($object));
        }
        return $object->$getter();
    }

    /**
     * @param array $objects
     * @return string[]
     */
    public function getIds(array $objects)
    {
        if(empty($objects)) {
            return [];
        }

        $object = reset($objects);
        $getter = $this->inflector->getterFromColumn($this->getIdColumn($object));
        if(!method_exists($object, $getter)) {
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
        if(method_exists($object, $setter)) {
            $object->$setter($id);
            return $object;
        }

        throw new MethodNotImplementedException("$setter must be implemented on ".get_class($object));
    }

    public function setNewId($object)
    {
        $this->setId($object, $this->queryHelper->getLastInsertId($this->convention->getTable($object), $this->getIdColumn($object)));
    }

    /**
     * @param string|object $objectOrClass
     * @return string Database column name containing the identifier for the object
     */
    public function getIdColumn($objectOrClass)
    {
        $annotations = $this->reader->getClassAnnotations($objectOrClass);
        if(isset($annotations['identifier'])) {
            if(is_string($annotations['identifier'])) {
                return $annotations['identifier'];
            }
        }

        return 'id';
    }
}