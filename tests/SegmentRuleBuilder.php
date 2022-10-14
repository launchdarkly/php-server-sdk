<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\SegmentRule;

class SegmentRuleBuilder
{
    /** @var Clause[] */
    private array $_clauses = [];
    private ?int $_weight = null;
    private ?string $_bucketBy = null;
    private ?string $_rolloutContextKind = null;

    public function build(): SegmentRule
    {
        return new SegmentRule($this->_clauses, $this->_weight, $this->_bucketBy, $this->_rolloutContextKind);
    }

    public function bucketBy(?string $bucketBy): SegmentRuleBuilder
    {
        $this->_bucketBy = $bucketBy;
        return $this;
    }

    public function clause(Clause $clause): SegmentRuleBuilder
    {
        $this->_clauses[] = $clause;
        return $this;
    }

    public function clauses(array $clauses): SegmentRuleBuilder
    {
        $this->_clauses = $clauses;
        return $this;
    }

    public function rolloutContextKind(?string $rolloutContextKind): SegmentRuleBuilder
    {
        $this->_rolloutContextKind = $rolloutContextKind;
        return $this;
    }

    public function weight(?int $weight): SegmentRuleBuilder
    {
        $this->_weight = $weight;
        return $this;
    }
}
