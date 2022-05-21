<?php
namespace Corma\DataObject\TableConvention;

use Corma\Util\Inflector;

class DefaultTableConvention implements TableConventionInterface
{
    public function __construct(protected Inflector $inflector)
    {
    }

    /**
     * @param object|string $classOrObject
     * @return string The database table name
     */
    public function getTable(object|string $classOrObject): string
    {
        $class = $this->inflector->getShortClass($classOrObject);
        return $this->inflector->tableize($this->inflector->pluralize($class));
    }
}
