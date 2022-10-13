<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Impl\Model\SegmentRule;

class SegmentBuilder
{
    private string $_key;
    private int $_version = 1;
    /** @var string[] */
    private array $_included = [];
    /** @var string[] */
    private array $_excluded = [];
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
            $this->_salt,
            $this->_rules,
            $this->_deleted
        );
    }

    public function excluded(array $excluded): SegmentBuilder
    {
        $this->_excluded = $excluded;
        return $this;
    }

    public function included(array $included): SegmentBuilder
    {
        $this->_included = $included;
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
}
