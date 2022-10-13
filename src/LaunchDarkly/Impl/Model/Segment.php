<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

use LaunchDarkly\LDContext;

/**
 * Internal data model class that describes a user segment.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class Segment
{
    protected string $_key;
    protected int $_version;
    /** @var string[] */
    protected array $_included = [];
    /** @var string[] */
    protected array $_excluded = [];
    protected string $_salt;
    /** @var SegmentRule[] */
    protected array $_rules = [];
    protected bool $_deleted = false;

    protected function __construct(
        string $key,
        int $version,
        array $included,
        array $excluded,
        string $salt,
        array $rules,
        bool $deleted
    ) {
        $this->_key = $key;
        $this->_version = $version;
        $this->_included = $included;
        $this->_excluded = $excluded;
        $this->_salt = $salt;
        $this->_rules = $rules;
        $this->_deleted = $deleted;
    }

    public static function getDecoder(): \Closure
    {
        return function (array $v) {
            return new Segment(
                $v['key'],
                $v['version'],
                $v['included'] ?: [],
                $v['excluded'] ?: [],
                $v['salt'],
                array_map(SegmentRule::getDecoder(), $v['rules'] ?: []),
                $v['deleted']
            );
        };
    }

    public static function decode(array $v): Segment
    {
        return static::getDecoder()($v);
    }

    public function matchesContext(LDContext $context): bool
    {
        $key = $context->getKey();
        if (!$key) {
            return false;
        }
        if (in_array($key, $this->_included, true)) {
            return true;
        }
        if (in_array($key, $this->_excluded, true)) {
            return false;
        }
        foreach ($this->_rules as $rule) {
            if ($rule->matchesContext($context, $this->_key, $this->_salt)) {
                return true;
            }
        }
        return false;
    }

    public function getVersion(): ?int
    {
        return $this->_version;
    }

    public function getKey(): string
    {
        return $this->_key;
    }

    public function isDeleted(): bool
    {
        return $this->_deleted;
    }
}
