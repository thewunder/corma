<?php

namespace Corma\DataObject\Identifier;

#[\Attribute]
class IdColumn
{
    private string $column;

    public function __construct(string $column)
    {
        $this->column = $column;
    }

    public function getColumn(): string
    {
        return $this->column;
    }
}