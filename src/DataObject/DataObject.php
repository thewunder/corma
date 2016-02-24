<?php
namespace Corma\DataObject;

use Doctrine\Common\Inflector\Inflector;

/**
 * An object that can be persisted and retrieved by a Corma ObjectRepository
 */
abstract class DataObject implements \JsonSerializable, DataObjectInterface
{
    protected $id, $isDeleted;

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
    public function setIsDeleted($isDeleted)
    {
        $this->isDeleted = (bool) $isDeleted;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsDeleted()
    {
        return (bool) $this->isDeleted;
    }

    /**
     * Sets the data provided to the properties of the object
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        if($this->id) {
            unset($data['id']);
        }
        foreach($data as $column => $value) {
            $setter = ucfirst($column);
            $setter = "set{$setter}";
            if(method_exists($this, $setter)) {
                $this->$setter($value);
            } else if(property_exists($this, $column) && !is_array($value)) {
                $this->{$column} = $value;
            }
        }
        return $this;
    }

    function jsonSerialize()
    {
        $vars = get_object_vars($this);
        foreach($vars as $name => &$value)
        {
            if(is_object($value)) {
                if(!$value instanceof DataObject || $value === $this) {
                    unset($vars[$name]);
                } else {
                    $vars[$name] = $value->jsonSerialize();
                }
            } else if(is_array($value)) {
                foreach($value as $k => $v) {
                    if($v instanceof DataObject) {
                        $value[$k] = $v->jsonSerialize();
                    }
                }
            } else if($value === null) {
                unset($vars[$name]);
            }
        }
        return (object) $vars;
    }
}