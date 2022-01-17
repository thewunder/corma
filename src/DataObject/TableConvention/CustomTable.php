<?php

namespace Corma\DataObject\TableConvention;

/**
 * Exists so there is an easy way to set a custom table name without annotations.
 */
class CustomTable implements TableConventionInterface
{
    private string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function getTable($classOrObject): string
    {
        return $this->table;
    }
}
