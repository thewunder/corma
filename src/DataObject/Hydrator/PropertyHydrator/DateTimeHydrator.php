<?php

namespace Corma\DataObject\Hydrator\PropertyHydrator;

use Corma\DataObject\Hydrator\PropertyHydrator\PropertyHydrator;
use Corma\Exception\InvalidArgumentException;

/**
 * Hydrates and extracts DateTime objects
 */
final class DateTimeHydrator implements PropertyHydrator
{
    public function propertyClass(): string
    {
        return \DateTimeInterface::class;
    }

    public function hydrate(string|int|float|bool $value, \ReflectionNamedType $type): \DateTimeInterface
    {
        $class = $type->getName();
        return new $class($value);
    }

    public function extract(object $value): string|int|float|bool
    {
        if (!($value instanceof \DateTimeInterface)) {
            throw new InvalidArgumentException('');
        }
        return $value->format('Y-m-d H:i:s');
    }
}
