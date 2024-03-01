<?php

namespace Corma\DataObject\TableConvention;


#[\Attribute(\Attribute::TARGET_CLASS)]
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
