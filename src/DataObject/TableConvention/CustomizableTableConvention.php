<?php
namespace Corma\DataObject\TableConvention;


use Corma\Exception\InvalidArgumentException;

/**
 * Allows for a customizable database table via the "#[TableName("custom_table")]" attribute
 */
class CustomizableTableConvention extends DefaultTableConvention
{
    /**
     * @param string|object $classOrObject
     * @return string The database table name
     */
    public function getTable($classOrObject): string
    {
        $reflectionClass = new \ReflectionClass($classOrObject);
        $attributes = $reflectionClass->getAttributes(DbTable::class);
        if (!empty($attributes)) {
            if (count($attributes) > 1) {
                throw new InvalidArgumentException('Only one DbTable attribute allowed');
            }
            /** @var DbTable $dbTable */
            $dbTable = $attributes[0]->newInstance();
            return $dbTable->getTable();
        }

        return parent::getTable($classOrObject);
    }
}
