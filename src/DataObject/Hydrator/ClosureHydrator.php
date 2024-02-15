<?php
namespace Corma\DataObject\Hydrator;

/**
 * Hydrates and extracts data via a closure bound to the object
 */
class ClosureHydrator implements ObjectHydratorInterface
{
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
        return function (array $data) {
            foreach ($data as $name => $value) {
                if ((is_scalar($value) || $value === null) && property_exists($this, $name)) {
                    $this->{$name} = $value;
                    continue;
                }

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
        return function () {
            $data = [];
            foreach ($this as $property => $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                $data[$property] = $value;
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
}
