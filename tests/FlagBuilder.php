<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Prerequisite;
use LaunchDarkly\Impl\Model\Rollout;
use LaunchDarkly\Impl\Model\Rule;
use LaunchDarkly\Impl\Model\Target;
use LaunchDarkly\Impl\Model\VariationOrRollout;

class FlagBuilder
{
    private string $_key;
    private int $_version = 1;
    private bool $_on = false;
    /** @var Prerequisite[] */
    private array $_prerequisites = [];
    private string $_salt = '';
    /** @var Target[] */
    private array $_targets = [];
    /** @var Target[] */
    private array $_contextTargets = [];
    /** @var Rule[] */
    private array $_rules = [];
    private VariationOrRollout $_fallthrough;
    private ?int $_offVariation = null;
    private array $_variations = [];
    private bool $_deleted = false;
    private bool $_trackEvents = false;
    private bool $_trackEventsFallthrough = false;
    private ?int $_debugEventsUntilDate = null;
    private bool $_clientSide = false;

    public function __construct(string $key)
    {
        $this->_key = $key;
    }

    public function build(): FeatureFlag
    {
        return new FeatureFlag(
            $this->_key,
            $this->_version,
            $this->_on,
            $this->_prerequisites,
            $this->_salt,
            $this->_targets,
            $this->_contextTargets,
            $this->_rules,
            $this->_fallthrough,
            $this->_offVariation,
            $this->_variations,
            $this->_deleted,
            $this->_trackEvents,
            $this->_trackEventsFallthrough,
            $this->_debugEventsUntilDate,
            $this->_clientSide
        );
    }

    public function contextTarget(string $contextKind, int $variation, string ...$values): FlagBuilder
    {
        $this->_contextTargets[] = new Target($contextKind, $values, $variation);
        return $this;
    }

    public function fallthroughRollout(Rollout $rollout): FlagBuilder
    {
        $this->_fallthrough = new VariationOrRollout(null, $rollout);
        return $this;
    }

    public function fallthroughVariation(int $variation): FlagBuilder
    {
        $this->_fallthrough = new VariationOrRollout($variation, null);
        return $this;
    }

    public function offVariation(?int $offVariation): FlagBuilder
    {
        $this->_offVariation = $offVariation;
        return $this;
    }

    public function on(bool $on): FlagBuilder
    {
        $this->_on = $on;
        return $this;
    }

    public function prerequisite(string $key, int $variation): FlagBuilder
    {
        $this->_prerequisites[] = new Prerequisite($key, $variation);
        return $this;
    }

    public function prerequisites(array $prerequisites): FlagBuilder
    {
        $this->_prerequisites = $prerequisites;
        return $this;
    }

    public function rule(Rule $rule): FlagBuilder
    {
        $this->_rules[] = $rule;
        return $this;
    }

    public function rules(array $rules): FlagBuilder
    {
        $this->_rules = $rules;
        return $this;
    }

    public function salt(string $salt): FlagBuilder
    {
        $this->_salt = $salt;
        return $this;
    }

    public function target(int $variation, string ...$values): FlagBuilder
    {
        $this->_targets[] = new Target(null, $values, $variation);
        return $this;
    }

    public function variations(...$variations): FlagBuilder
    {
        $this->_variations = $variations;
        return $this;
    }
}
