<?php
namespace Corma\Test\Fixtures;

/**
 * @table custom_table
 * @identifier custom_id
 */
class AnnotatedDataObject
{
    protected $custom_id;

    /**
     * @return mixed
     */
    public function getCustomId()
    {
        return $this->custom_id;
    }

    /**
     * @param mixed $custom_id
     * @return AnnotatedDataObject
     */
    public function setCustomId($custom_id)
    {
        $this->custom_id = $custom_id;
        return $this;
    }
}