<?php

namespace Corma\DataObject\TableConvention;


#[\Attribute]
class DbTable
{
    private string $name;

    public function __construct(string $table)
    {
        $this->name = $table;
    }

    public function getTable(): string
    {
        return $this->name;
    }
}