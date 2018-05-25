<?php
namespace LaunchDarkly;

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
