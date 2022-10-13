<?php

declare(strict_types=1);

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
    private array $_values = [];
    private int $_variation;

    public function __construct(array $values, int $variation)
    {
        $this->_values = $values;
        $this->_variation = $variation;
    }

    public static function getDecoder(): \Closure
    {
        return fn (array $v) => new Target($v['values'], $v['variation']);
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
