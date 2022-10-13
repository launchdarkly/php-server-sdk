<?php

declare(strict_types=1);

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
    private string $_key;
    private int $_variation;

    public function __construct(string $key, int $variation)
    {
        $this->_key = $key;
        $this->_variation = $variation;
    }

    public static function getDecoder(): \Closure
    {
        return fn (array $v) => new Prerequisite($v['key'], $v['variation']);
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
