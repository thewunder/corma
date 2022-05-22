<?php
namespace Corma\Test\Fixtures;

class OtherDataObject extends BaseDataObject
{
    protected string $name = '';
    protected ?int $extendedDataObjectId = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getExtendedDataObjectId(): ?int
    {
        return $this->extendedDataObjectId;
    }

    public function setExtendedDataObjectId(?int $extendedDataObjectId): static
    {
        $this->extendedDataObjectId = $extendedDataObjectId;
        return $this;
    }
}
