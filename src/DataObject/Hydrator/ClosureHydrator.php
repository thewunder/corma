<?php
namespace Corma\DataObject\Hydrator;

class ClosureHydrator implements ObjectHydratorInterface
{
    /** @var  \Closure */
    protected $hydrateClosure;

    /** @var  \Closure */
    protected $extractClosure;

    /**
     * Sets the supplied data on to the object.
     * This implementation sets properties directly for scalar values (to mimic PDO), and calls setters for non scalar data.
     *
     * @param object $object
     * @param array $data
     * @return object
     */
    public function hydrate($object, array $data)
    {
        if(!$this->hydrateClosure) {
            $this->hydrateClosure = function () use ($data) {
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

        $this->hydrateClosure->bindTo($object, $object)->__invoke();

        return $object;
    }

    public function extract($object)
    {
        if(!$this->extractClosure) {
            $this->extractClosure = function () {
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

        return $this->extractClosure->bindTo($object, $object)->__invoke();
    }
}