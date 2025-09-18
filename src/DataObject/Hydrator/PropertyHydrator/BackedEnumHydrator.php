<?php

namespace Corma\DataObject\Hydrator\PropertyHydrator;

use Corma\DataObject\Hydrator\PropertyHydrator\PropertyHydrator;

/**
 * Hydrates and extracts BackedEnum objects
 */
final class BackedEnumHydrator implements PropertyHydrator
{
    public function propertyClass(): string
    {
        return \BackedEnum::class;
    }

    public function hydrate(string|int|float|bool $value, \ReflectionNamedType $type): \BackedEnum
    {
        /** @var \BackedEnum $enumType */
        $enumType = $type->getName();
        return $enumType::from($value);
    }

    public function extract(object $value): string|int|float|bool
    {
        if (!($value instanceof \BackedEnum)) {
            throw new \InvalidArgumentException('Must be a BackedEnum');
        }
        return $value->value;
    }
}
