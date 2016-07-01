<?php
namespace Corma\Test\Fixtures;

use Corma\DataObject\DataObject;
use Corma\ObjectMapper;

class WithDependencies extends DataObject
{
    /**
     * @var ObjectMapper
     */
    private $orm;

    public function __construct(ObjectMapper $orm)
    {
        $this->orm = $orm;
    }

    /**
     * @return ObjectMapper
     */
    public function getOrm()
    {
        return $this->orm;
    }
}