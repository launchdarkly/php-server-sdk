<?php
namespace LaunchDarkly\Impl\Model;

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
    private $_key;
    /** @var int */
    private $_variation;

    protected function __construct(string $key, int $variation)
    {
        $this->_key = $key;
        $this->_variation = $variation;
    }

    public static function getDecoder(): \Closure
    {
        return function (array $v) {
            return new Prerequisite($v['key'], $v['variation']);
        };
    }

    public function getKey(): string
    {
        return $this->_key;
    }

    public function getVariation(): int
    {
        return $this->_variation;
    }
}
