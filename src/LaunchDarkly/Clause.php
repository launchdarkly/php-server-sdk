<?php

namespace LaunchDarkly;

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
    private $_values = array();
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

    public function matchesUser(LDUser $user, ?FeatureRequester $featureRequester): bool
    {
        if ($this->_op === 'segmentMatch') {
            foreach ($this->_values as $value) {
                $segment = $featureRequester ? $featureRequester->getSegment($value) : null;
                if ($segment) {
                    if ($segment->matchesUser($user)) {
                        return $this->_maybeNegate(true);
                    }
                }
            }
            return $this->_maybeNegate(false);
        } else {
            return $this->matchesUserNoSegments($user);
        }
    }

    public function matchesUserNoSegments(LDUser $user): bool
    {
        $userValue = $user->getValueForEvaluation($this->_attribute);
        if ($userValue === null) {
            return false;
        }
        if (is_array($userValue)) {
            foreach ($userValue as $element) {
                if ($this->matchAny($element)) {
                    return $this->_maybeNegate(true);
                }
            }
            return $this->_maybeNegate(false);
        } else {
            return $this->_maybeNegate($this->matchAny($userValue));
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
     * @param mixed|null $userValue
     *
     * @return bool
     */
    private function matchAny($userValue): bool
    {
        foreach ($this->_values as $v) {
            $result = Operators::apply($this->_op, $userValue, $v);
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
