<?php
namespace LaunchDarkly;

/**
 * Internal data model class that describes a feature flag prerequisite.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class Prerequisite
{
    /** @var string */
    private $_key = null;
    /** @var int */
    private $_variation = null;

    protected function __construct($key, $variation)
    {
        $this->_key = $key;
        $this->_variation = $variation;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new Prerequisite($v['key'], $v['variation']);
        };
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * @return int
     */
    public function getVariation()
    {
        return $this->_variation;
    }
}
