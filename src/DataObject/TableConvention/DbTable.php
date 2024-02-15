<?php

namespace Corma\DataObject\TableConvention;


#[\Attribute]
class DbTable
{
    public function __construct(private readonly string $table)
    {
    }

    public function getTable(): string
    {
        return $this->table;
    }
}
