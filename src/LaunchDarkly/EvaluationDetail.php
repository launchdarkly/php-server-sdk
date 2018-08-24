<?php

namespace LaunchDarkly;

/**
 * An object returned by LDClient.variationDetail(), combining the result of a flag evaluation with
 * an explanation of how it was calculated.
 */
class EvaluationDetail
{
    private $_variationIndex = null;
    private $_value = null;
    private $_reason = null;

    /**
     * EvaluationDetail constructor.
     * @param mixed $value the value of the flag variation
     * @param int|null $variationIndex the index of the flag variation, or null if it was the default value
     * @param EvaluationReason $reason evaluation reason properties
     */
    public function __construct($value, $variationIndex, $reason = null)
    {
        $this->_value = $value;
        $this->_variationIndex = $variationIndex;
        $this->_reason = $reason;
    }

    /**
     * Returns the value of the flag variation for the user.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * Returns the index of the flag variation for the user, e.g. 0 for the first variation -
     * or null if it was the default value.
     *
     * @return int | null
     */
    public function getVariationIndex()
    {
        return $this->_variationIndex;
    }

    /**
     * Returns information about how the flag value was calculated.
     *
     * @return EvaluationReason
     */
    public function getReason()
    {
        return $this->_reason;
    }

    /**
     * Returns true if the flag evaluated to the default value, rather than one of its variations.
     *
     * @return bool
     */
    public function isDefaultValue()
    {
        return ($this->_variationIndex === null);
    }
}
