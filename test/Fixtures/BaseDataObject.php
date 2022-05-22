<?php
namespace Corma\Test\Fixtures;

class BaseDataObject
{
    protected ?int $id = null;
    protected bool $isDeleted = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
    }
}
