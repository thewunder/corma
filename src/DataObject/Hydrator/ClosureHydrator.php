<?php
namespace Corma\DataObject\Hydrator;

/**
 * Hydrates and extracts data via a closure bound to the object
 */
class ClosureHydrator implements ObjectHydratorInterface
{
    /** @var  \Closure */
    protected $hydrateClosure;

    /** @var  \Closure */
    protected $extractClosure;

    public function __construct(\Closure $hydrate = null, \Closure $extract = null)
    {
        $this->hydrateClosure = $hydrate;
        $this->extractClosure = $extract;
    }

    public function hydrate(object $object, array $data)
    {
        if (!$this->hydrateClosure) {
            $this->hydrateClosure = self::getDefaultHydrate();
        }

        $this->hydrateClosure->bindTo($object, $object)->__invoke($data);

        return $object;
    }

    public function extract(object $object): array
    {
        if (!$this->extractClosure) {
            $this->extractClosure = self::getDefaultExtract();
        }

        return $this->extractClosure->bindTo($object, $object)->__invoke();
    }

    /**
     * This implementation sets properties directly for scalar values (to mimic PDO), and calls setters for non scalar data.
     *
     * @return \Closure
     */
    public function getDefaultHydrate()
    {
        return function (array $data) {
            foreach ($data as $name => $value) {
                if (is_scalar($value) && property_exists($this, $name)) {
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

    /**
     * @return \Closure
     */
    public function getDefaultExtract()
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

    /**
     * @param \Closure $hydrateClosure
     */
    public function setHydrateClosure(\Closure $hydrateClosure)
    {
        $this->hydrateClosure = $hydrateClosure;
    }

    /**
     * @param \Closure $extractClosure
     */
    public function setExtractClosure(\Closure $extractClosure)
    {
        $this->extractClosure = $extractClosure;
    }
}
