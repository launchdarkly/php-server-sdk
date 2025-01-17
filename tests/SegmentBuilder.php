<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Impl\Model\SegmentRule;
use LaunchDarkly\Impl\Model\SegmentTarget;

class SegmentBuilder
{
    private string $_key;
    private int $_version = 1;
    /** @var string[] */
    private array $_included = [];
    /** @var string[] */
    private array $_excluded = [];
    /** @var SegmentTarget[] */
    private array $_includedContexts = [];
    /** @var SegmentTarget[] */
    private array $_excludedContexts = [];
    private bool $_unbounded = false;
    private ?string $_unboundedContextKind = null;
    private ?int $_generation = null;
    private string $_salt = '';
    /** @var SegmentRule[] */
    private array $_rules = [];
    private bool $_deleted = false;

    public function __construct(string $key)
    {
        $this->_key = $key;
    }

    public function build(): Segment
    {
        return new Segment(
            $this->_key,
            $this->_version,
            $this->_included,
            $this->_excluded,
            $this->_includedContexts,
            $this->_excludedContexts,
            $this->_unbounded,
            $this->_unboundedContextKind,
            $this->_generation,
            $this->_salt,
            $this->_rules,
            $this->_deleted
        );
    }

    public function excluded(string ...$excluded): SegmentBuilder
    {
        $this->_excluded = $excluded;
        return $this;
    }

    public function excludedContexts(string $contextKind, string ...$excluded): SegmentBuilder
    {
        $this->_excludedContexts[] = new SegmentTarget($contextKind, $excluded);
        return $this;
    }

    public function included(string ...$included): SegmentBuilder
    {
        $this->_included = $included;
        return $this;
    }

    public function includedContexts(string $contextKind, string ...$included): SegmentBuilder
    {
        $this->_includedContexts[] = new SegmentTarget($contextKind, $included);
        return $this;
    }

    public function rule(SegmentRule $rule): SegmentBuilder
    {
        $this->_rules[] = $rule;
        return $this;
    }

    public function rules(array $rules): SegmentBuilder
    {
        $this->_rules = $rules;
        return $this;
    }

    public function salt(string $salt): SegmentBuilder
    {
        $this->_salt = $salt;
        return $this;
    }

    public function unbounded(bool $unbounded): SegmentBuilder
    {
        $this->_unbounded = $unbounded;
        return $this;
    }

    public function unboundedContextKind(?string $unboundedContextKind): SegmentBuilder
    {
        $this->_unboundedContextKind = $unboundedContextKind;
        return $this;
    }

    public function generation(?int $generation): SegmentBuilder
    {
        $this->_generation = $generation;
        return $this;
    }
}
