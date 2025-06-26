<?php
namespace Corma\DataObject\Hydrator;

/**
 * Hydrates and extracts data via reflection
 */
final class ReflectionHydrator implements ObjectHydratorInterface
{
    private ?\ReflectionClass $reflectionClass = null;
    private array $typeToHydratorMap = [];

    /**
     * @param PropertyHydrator[] $propertyHydrators
     */
    public function __construct(array $propertyHydrators = [])
    {
        foreach ($propertyHydrators as $hydrator) {
            foreach ($hydrator->getSupportedTypes() as $type) {
                $this->typeToHydratorMap[$type][] = $hydrator;
            }
        }
    }


    public function hydrate(object $object, array $data): object
    {
        $this->getReflectionClass($object);

        foreach ($data as $name => $value) {
            if ($this->reflectionClass->hasProperty($name)) {
                $property = $this->reflectionClass->getProperty($name);
                if (is_scalar($value)) {
                    $property->setValue($object, $value);
                }
            }
        }

        return $object;
    }

    public function extract(object $object): array
    {
        $this->getReflectionClass($object);

        $data = [];
        foreach ($this->reflectionClass->getProperties() as $property) {
            $value = $property->getValue($object);

            if (is_scalar($value) || $value === null) {
                $data[$property->getName()] = $value;
            }
        }

        return $data;
    }

    private function getReflectionClass(object $object): void
    {
        if ($this->reflectionClass) {
            $this->reflectionClass = new \ReflectionClass($object);
        }
    }
}
