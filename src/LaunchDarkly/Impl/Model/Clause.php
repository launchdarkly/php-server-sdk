<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

/**
 * Internal data model class that describes a clause within a feature flag rule or segment rule.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class Clause
{
    private ?string $_attribute = null;
    private ?string $_op = null;
    private array $_values = [];
    private bool $_negate = false;

    public function __construct(?string $attribute, ?string $op, array $values, bool $negate)
    {
        $this->_attribute = $attribute;
        $this->_op = $op;
        $this->_values = $values;
        $this->_negate = $negate;
    }

    /**
     * @psalm-return \Closure(mixed):self
     */
    public static function getDecoder(): \Closure
    {
        return fn ($v) => new Clause($v['attribute'], $v['op'], $v['values'], $v['negate']);
    }

    public function getAttribute(): ?string
    {
        return $this->_attribute;
    }

    public function getOp(): ?string
    {
        return $this->_op;
    }

    public function getValues(): array
    {
        return $this->_values;
    }

    public function isNegate(): bool
    {
        return $this->_negate;
    }
}
