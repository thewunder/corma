<?php
namespace Corma\Test\Fixtures;

use Corma\Relationship\ManyToMany;
use Corma\Relationship\OneToMany;
use Corma\Relationship\OneToOne;
use Corma\Relationship\Polymorphic;

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

    #[OneToOne]
    protected ?OtherDataObject $otherDataObject = null;

    /** @var OtherDataObject[] */
    #[OneToMany(OtherDataObject::class)]
    protected ?array $otherDataObjects = null;

    #[ManyToMany(OtherDataObject::class, 'extended_other_rel')]
    protected ?array $manyToManyOtherDataObjects = null;

    #[ManyToMany(OtherDataObject::class, 'extended_other_rel', shallow: true)]
    protected ?array $shallowOtherDataObjects = null;

    protected ?int $polymorphicId = null;
    protected ?string $polymorphicClass = null;

    #[Polymorphic]
    protected ?object $polymorphic = null;

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
        return $this->otherDataObjects ?? [];
    }

    /**
     * @return OtherDataObject[]
     */
    public function getCustoms(): array
    {
        return $this->custom;
    }

    /**
     * @param OtherDataObject[] $custom
     */
    public function setCustoms(array $custom): void
    {
        $this->custom = $custom;
    }

    /**
     * @return OtherDataObject[]|null
     */
    public function getManyToManyOtherDataObjects(): ?array
    {
        return $this->manyToManyOtherDataObjects;
    }

    /**
     * @param OtherDataObject[] $otherDataObjects
     */
    public function setManyToManyOtherDataObjects(array $otherDataObjects): static
    {
        $this->manyToManyOtherDataObjects = $otherDataObjects;
        return $this;
    }

    /**
     * @return OtherDataObject[]|null
     */
    public function getShallowOtherDataObjects(): ?array
    {
        return $this->shallowOtherDataObjects;
    }

    /**
     * @param OtherDataObject[] $otherDataObjects
     */
    public function setShallowOtherDataObjects(array $otherDataObjects): static
    {
        $this->shallowOtherDataObjects = $otherDataObjects;
        return $this;
    }

    public function getPolymorphicId(): ?int
    {
        return $this->polymorphicId;
    }

    public function setPolymorphicId(?int $polymorphicId): void
    {
        $this->polymorphicId = $polymorphicId;
    }

    public function getPolymorphicClass(): ?string
    {
        return $this->polymorphicClass;
    }

    public function setPolymorphicClass(?string $polymorphicClass): void
    {
        $this->polymorphicClass = $polymorphicClass;
    }

    public function getPolymorphic(): ?object
    {
        return $this->polymorphic;
    }

    public function setPolymorphic(?object $polymorphic): void
    {
        $this->polymorphic = $polymorphic;
    }
}
