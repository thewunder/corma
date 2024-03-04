<?php

namespace Corma\Relationship;

use Corma\ObjectMapper;
use Corma\Util\Inflector;

abstract class BaseRelationshipHandler implements RelationshipHandler
{
    protected readonly Inflector $inflector;
    public function __construct(protected readonly ObjectMapper $objectMapper)
    {
        $this->inflector = $this->objectMapper->getInflector();
    }
}
