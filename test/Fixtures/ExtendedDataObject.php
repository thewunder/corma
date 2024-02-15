<?php
namespace Corma\Test\Fixtures;

/**
 * A Fixture
 */
class ExtendedDataObject extends BaseDataObject
{
    protected string $myColumn = '';
    protected ?int $myNullableColumn = null;
    protected ?int $otherDataObjectId = null;

    protected ?array $arrayProperty = null;
    protected ?ExtendedDataObject $objectProperty = null;
    protected ?OtherDataObject $otherDataObject = null;

    /** @var OtherDataObject[] */
    protected ?array $otherDataObjects= null;

    /** @var OtherDataObject[] */
    protected ?array $custom = null;

    public function getMyColumn(): string
    {
        return $this->myColumn;
    }

    public function setMyColumn(string $myColumn): static
    {
        $this->myColumn = $myColumn;
        return $this;
    }

    public function getMyNullableColumn(): ?int
    {
        return $this->myNullableColumn;
    }

    public function setMyNullableColumn(?int $myNullableColumn): static
    {
        $this->myNullableColumn = $myNullableColumn;
        return $this;
    }

    public function getArrayProperty(): array
    {
        return $this->arrayProperty;
    }

    public function setArrayProperty(array $arrayProperty): static
    {
        $this->arrayProperty = $arrayProperty;
        return $this;
    }

    public function getObjectProperty(): ?ExtendedDataObject
    {
        return $this->objectProperty;
    }

    public function setObjectProperty(ExtendedDataObject $objectProperty): static
    {
        $this->objectProperty = $objectProperty;
        return $this;
    }

    public function getOtherDataObjectId(): ?int
    {
        return $this->otherDataObjectId;
    }

    public function setOtherDataObjectId(?int $otherDataObjectId): static
    {
        $this->otherDataObjectId = $otherDataObjectId;
        return $this;
    }

    public function getOtherDataObject(): ?OtherDataObject
    {
        return $this->otherDataObject;
    }

    public function setOtherDataObject(OtherDataObject $otherDataObject): static
    {
        $this->otherDataObject = $otherDataObject;
        return $this;
    }

    /**
     * @param OtherDataObject[] $otherDataObjects
     * @return ExtendedDataObject
     */
    public function setOtherDataObjects(array $otherDataObjects): static
    {
        $this->otherDataObjects = $otherDataObjects;
        return $this;
    }

    /**
     * @return OtherDataObject[]
     */
    public function getOtherDataObjects(): array
    {
        return $this->otherDataObjects;
    }

    /**
     * @return OtherDataObject[]
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * @param OtherDataObject[] $custom
     */
    public function setCustom(array $custom): void
    {
        $this->custom = $custom;
    }
}
