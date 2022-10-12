<?php

namespace LaunchDarkly\Impl\Model;

use LaunchDarkly\FeatureRequester;
use LaunchDarkly\LDContext;

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
    /** @var string|null */
    private $_attribute = null;
    /** @var string|null */
    private $_op = null;
    /** @var array  */
    private $_values = [];
    /** @var bool  */
    private $_negate = false;

    private function __construct(?string $attribute, ?string $op, array $values, bool $negate)
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
        return function ($v) {
            return new Clause($v['attribute'], $v['op'], $v['values'], $v['negate']);
        };
    }

    public function matchesContext(LDContext $context, ?FeatureRequester $featureRequester): bool
    {
        if ($this->_op === 'segmentMatch') {
            foreach ($this->_values as $value) {
                $segment = $featureRequester ? $featureRequester->getSegment($value) : null;
                if ($segment) {
                    if ($segment->matchesContext($context)) {
                        return $this->_maybeNegate(true);
                    }
                }
            }
            return $this->_maybeNegate(false);
        } else {
            return $this->matchesContextNoSegments($context);
        }
    }

    public function matchesContextNoSegments(LDContext $context): bool
    {
        if ($this->_attribute === null) {
            return false;
        }
        $contextValue = $context->get($this->_attribute);
        if ($contextValue === null) {
            return false;
        }
        if (is_array($contextValue)) {
            foreach ($contextValue as $element) {
                if ($this->matchAny($element)) {
                    return $this->_maybeNegate(true);
                }
            }
            return $this->_maybeNegate(false);
        } else {
            return $this->_maybeNegate($this->matchAny($contextValue));
        }
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

    /**
     * @param mixed|null $contextValue
     *
     * @return bool
     */
    private function matchAny($contextValue): bool
    {
        foreach ($this->_values as $v) {
            $result = Operators::apply($this->_op, $contextValue, $v);
            if ($result === true) {
                return true;
            }
        }
        return false;
    }

    private function _maybeNegate(bool $b): bool
    {
        if ($this->_negate) {
            return !$b;
        } else {
            return $b;
        }
    }
}
