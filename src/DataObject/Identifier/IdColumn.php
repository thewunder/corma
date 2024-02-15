<?php

namespace Corma\DataObject\Identifier;

#[\Attribute]
class IdColumn
{
    public function __construct(private readonly string $column)
    {
    }

    public function getColumn(): string
    {
        return $this->column;
    }
}
