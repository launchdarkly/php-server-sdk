<?php

namespace LaunchDarkly;

class EvalResult
{
    private $_variation = null;
    private $_value = null;
    /** @var array */
    private $_prerequisiteEvents = [];

    /**
     * EvalResult constructor.
     * @param null $value
     * @param array $prerequisiteEvents
     */
    public function __construct($variation, $value, array $prerequisiteEvents)
    {
        $this->_variation = $variation;
        $this->_value = $value;
        $this->_prerequisiteEvents = $prerequisiteEvents;
    }

    /**
     * @return int | null
     */
    public function getVariation()
    {
        return $this->_variation;
    }

    /**
     * @return null
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * @return array
     */
    public function getPrerequisiteEvents()
    {
        return $this->_prerequisiteEvents;
    }
}
