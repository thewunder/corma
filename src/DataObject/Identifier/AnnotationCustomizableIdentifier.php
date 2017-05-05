<?php
namespace Corma\DataObject\Identifier;

use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;

/**
 * Allows for a customizable identifier column via the "@identifier" annotation
 */
abstract class AnnotationCustomizableIdentifier extends BaseIndentifier
{
    /**
     * @var ReaderInterface
     */
    protected $reader;

    public function __construct(Inflector $inflector, ReaderInterface $reader = null)
    {
        parent::__construct($inflector);
        $this->reader = $reader;
    }

    public function getIdColumn($objectOrClass): string
    {
        if ($this->reader) {
            $annotations = $this->reader->getClassAnnotations($objectOrClass);
            if (isset($annotations['identifier'])) {
                if (is_string($annotations['identifier'])) {
                    return $annotations['identifier'];
                }
            }
        }

        return parent::getIdColumn($objectOrClass);
    }
}
