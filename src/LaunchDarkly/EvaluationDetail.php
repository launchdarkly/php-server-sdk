<?php

declare(strict_types=1);

namespace LaunchDarkly;

/**
 * An object that combines the result of a flag evaluation with an explanation of how it was calculated.
 *
 * This is returned by {@see \LaunchDarkly\LDClient::variationDetail()}.
 */
class EvaluationDetail
{
    private ?int $_variationIndex = null;
    private mixed $_value = null;
    private EvaluationReason $_reason;

    /**
     * EvaluationDetail constructor.
     * @param mixed $value the value of the flag variation
     * @param int|null $variationIndex the index of the flag variation, or null if it was the default value
     * @param EvaluationReason $reason evaluation reason properties
     */
    public function __construct(mixed $value, ?int $variationIndex, EvaluationReason $reason)
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
    public function getValue(): mixed
    {
        return $this->_value;
    }

    /**
     * Returns the index of the flag variation for the user, e.g. 0 for the first variation -
     * or null if it was the default value.
     *
     * @return int | null
     */
    public function getVariationIndex(): ?int
    {
        return $this->_variationIndex;
    }

    /**
     * Returns information about how the flag value was calculated.
     *
     * @return EvaluationReason
     */
    public function getReason(): EvaluationReason
    {
        return $this->_reason;
    }

    /**
     * Returns true if the flag evaluated to the default value, rather than one of its variations.
     *
     * @return bool
     */
    public function isDefaultValue(): bool
    {
        return ($this->_variationIndex === null);
    }
}
