<?php
namespace Corma\DataObject\Hydrator;

class PropertyObjectHydrator implements ObjectHydratorInterface
{
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
        $closure = function () use ($data) {
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

        $closure->bindTo($object, $object)->__invoke();

        return $object;
    }

    /**
     * Extracts all scalar data from the object
     *
     * @param object $object
     * @return array
     */
    public function extract($object)
    {
        $closure = function () {
            $data = [];
            foreach ($this as $property => $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                $data[$property] = $value;
            }
            return $data;
        };

        return $closure->bindTo($object, $object)->__invoke();
    }
}