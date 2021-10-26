<?php

namespace LaunchDarkly\Impl\Model;

/**
 * Internal data model class that describes a feature flag user targeting list.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class Target
{
    /** @var string[] */
    private $_values = [];
    /** @var int */
    private $_variation;

    protected function __construct(array $values, int $variation)
    {
        $this->_values = $values;
        $this->_variation = $variation;
    }

    public static function getDecoder(): \Closure
    {
        return function (array $v) {
            $values = $v['values'];
            $variation = $v['variation'];
            return new Target($values, $variation);
        };
    }

    /**
     * @return \string[]
     */
    public function getValues(): array
    {
        return $this->_values;
    }

    public function getVariation(): int
    {
        return $this->_variation;
    }
}
