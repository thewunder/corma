<?php

namespace Corma\DataObject\TableConvention;

/**
 * Exists so there is an easy way to set a custom table name without annotations.
 */
class CustomTable implements TableConventionInterface
{
    public function __construct(private string $table)
    {
    }

    public function getTable($classOrObject): string
    {
        return $this->table;
    }
}
