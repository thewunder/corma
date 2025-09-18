<?php

namespace Corma\DataObject\Hydrator\PropertyHydrator;

/**
 * A delegate for hydration and extraction of a single property
 */
interface PropertyHydrator
{
    /**
     * The class of the property that this hydrator handles
     * @return string
     */
    public function propertyClass(): string;

    /**
     * @param string|int|float|bool $value Value from database
     * @param \ReflectionNamedType $type Type of the property
     * @return object Value to set on the object
     */
    public function hydrate(string|int|float|bool $value, \ReflectionNamedType $type): object;

    /**
     * @param mixed $value Value to extract data from
     * @return string|int|float|bool Value to write to the database
     */
    public function extract(object $value): string|int|float|bool;
}
