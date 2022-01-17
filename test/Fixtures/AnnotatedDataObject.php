<?php
namespace Corma\Test\Fixtures;

use Corma\DataObject\Identifier\IdColumn;
use Corma\DataObject\TableConvention\DbTable;

#[DbTable("custom_table")]
#[IdColumn("custom_id")]
class AnnotatedDataObject
{
    protected string $custom_id;

    public function getCustomId(): string
    {
        return $this->custom_id;
    }

    public function setCustomId(string $custom_id): AnnotatedDataObject
    {
        $this->custom_id = $custom_id;
        return $this;
    }
}
