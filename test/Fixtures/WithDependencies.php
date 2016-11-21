<?php
namespace Corma\Test\Fixtures;

use Corma\ObjectMapper;

class WithDependencies extends BaseDataObject
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
