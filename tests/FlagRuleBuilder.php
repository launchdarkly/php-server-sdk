<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\Rollout;
use LaunchDarkly\Impl\Model\Rule;

class FlagRuleBuilder
{
    /** @var Clause[] */
    private array $_clauses = [];
    private string $_id = '';
    private ?int $_variation = null;
    private ?Rollout $_rollout = null;
    private bool $_trackEvents = false;

    public function build(): Rule
    {
        return new Rule($this->_variation, $this->_rollout, $this->_id, $this->_clauses, $this->_trackEvents);
    }

    public function clause(Clause $clause): FlagRuleBuilder
    {
        $this->_clauses[] = $clause;
        return $this;
    }

    public function clauses(array $clauses): FlagRuleBuilder
    {
        $this->_clauses = $clauses;
        return $this;
    }

    public function id(string $id): FlagRuleBuilder
    {
        $this->_id = $id;
        return $this;
    }

    public function rollout(Rollout $rollout): FlagRuleBuilder
    {
        $this->_rollout = $rollout;
        $this->_variation = null;
        return $this;
    }

    public function trackEvents(bool $trackEvents): FlagRuleBuilder
    {
        $this->_trackEvents = $trackEvents;
        return $this;
    }

    public function variation(int $variation): FlagRuleBuilder
    {
        $this->_variation = $variation;
        $this->_rollout = null;
        return $this;
    }
}
