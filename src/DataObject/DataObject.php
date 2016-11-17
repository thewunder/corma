<?php
namespace Corma\DataObject;

use Doctrine\Common\Inflector\Inflector;

/**
 * An object that can be persisted and retrieved by Corma
 */
abstract class DataObject implements DataObjectInterface
{
    protected $id;
    protected $isDeleted;

    public function __construct()
    {
    }

    /**
     * Get the table this data object is persisted in
     *
     * @return string
     */
    public static function getTableName()
    {
        $class = self::getClassName();
        return Inflector::tableize(Inflector::pluralize($class));
    }

    /**
     * Get class minus namespace
     *
     * @return string
     */
    public static function getClassName()
    {
        $class = get_called_class();
        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * @param object[] $objects
     * @return array
     */
    public static function getIds(array $objects)
    {
        return array_map(function ($object) {
            return $object->getId();
        }, $objects);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param bool $isDeleted
     * @return $this
     */
    public function setDeleted($isDeleted)
    {
        $this->isDeleted = (bool) $isDeleted;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return (bool) $this->isDeleted;
    }

    /**
     * Sets the data provided to the properties of the object. Used when restoring objects from cache.
     * Scalar data will set properties directly, while non-scalar data requires a setter.
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        if ($this->id) {
            unset($data['id']);
        }

        foreach ($data as $name => $value) {
            $setter = ucfirst($name);
            $setter = "set{$setter}";
            if (is_scalar($value) && property_exists($this, $name)) {
                $this->{$name} = $value;
            } elseif (method_exists($this, $setter)) {
                $this->$setter($value);
            }
        }
        return $this;
    }
    
    public function getData()
    {
        $data = [];
        foreach ($this as $property => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $data[$property] = $value;
        }
        return $data;
    }
}
