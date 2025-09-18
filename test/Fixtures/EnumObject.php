<?php
namespace Corma\Test\Fixtures;

class EnumObject
{
    private TestEnum $status = TestEnum::ACTIVE;

    public function getStatus(): TestEnum
    {
        return $this->status;
    }
}
