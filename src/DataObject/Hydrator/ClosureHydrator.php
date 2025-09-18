<?php
namespace Corma\DataObject\Hydrator;

use Corma\DataObject\Hydrator\PropertyHydrator\PropertyHydrator;

/**
 * Hydrates and extracts data via a closure bound to the object
 */
class ClosureHydrator implements ObjectHydratorInterface
{
    /**
     * @var PropertyHydrator[]
     */
    protected array $propertyHydrators = [];

    public function __construct(protected ?\Closure $hydrate = null, protected ?\Closure $extract = null)
    {
    }

    public function hydrate(object $object, array $data): object
    {
        if (!$this->hydrate) {
            $this->hydrate = self::getDefaultHydrate();
        }

        $this->hydrate->bindTo($object, $object)->__invoke($data);

        return $object;
    }

    public function extract(object $object): array
    {
        if (!$this->extract) {
            $this->extract = self::getDefaultExtract();
        }

        return $this->extract->bindTo($object, $object)->__invoke();
    }

    /**
     * This implementation sets properties directly for scalar values (to mimic PDO), and calls setters for non-scalar data.
     *
     * @return \Closure
     */
    public function getDefaultHydrate(): \Closure
    {
        $hydrators = $this->propertyHydrators;
        return function (array $data) use ($hydrators) {
            foreach ($data as $name => $value) {
                // Handle direct property assignment for scalar and null values
                if ((is_scalar($value) || $value === null) && property_exists($this, $name)) {
                    $reflection = new \ReflectionProperty($this, $name);
                    $type = $reflection->getType();
                    if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                        if ($value === null) {
                            continue;
                        }
                        $typeName = $type->getName();
                        // Find a hydrator that can handle this type or its parent types
                        foreach ($hydrators as $class => $hydrator) {
                            if (is_a($typeName, $class, true)) {
                                $this->{$name} = $hydrator->hydrate($value, $type);
                                break;
                            }
                        }
                    } else {
                        $this->{$name} = $value;
                    }

                    continue;
                }

                // Use setters for non-scalar data
                $setter = ucfirst($name);
                $setter = "set{$setter}";
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                }
            }
        };
    }

    public function getDefaultExtract(): \Closure
    {
        $hydrators = $this->propertyHydrators;
        return function () use ($hydrators) {
            $data = [];
            foreach ($this as $property => $value) {
                // Include null and scalar values
                if (is_scalar($value) || $value === null) {
                    $data[$property] = $value;
                } elseif (is_object($value)) {
                    // Check if there's a PropertyHydrator that can handle this object
                    foreach ($hydrators as $class => $hydrator) {
                        if ($value instanceof $class) {
                            $data[$property] = $hydrator->extract($value);
                            break;
                        }
                    }
                }
            }
            return $data;
        };
    }

    public function setHydrate(\Closure $hydrate): void
    {
        $this->hydrate = $hydrate;
    }

    public function setExtract(\Closure $extract): void
    {
        $this->extract = $extract;
    }

    /**
     * Add a property hydrator
     */
    public function addPropertyHydrator(PropertyHydrator $propertyHydrator): void
    {
        $this->propertyHydrators[$propertyHydrator->propertyClass()] = $propertyHydrator;
    }

    /**
     * Get a property hydrator for the given class if one exists
     */
    public function getPropertyHydrator(string $class): ?PropertyHydrator
    {
        return $this->propertyHydrators[$class] ?? null;
    }
}
