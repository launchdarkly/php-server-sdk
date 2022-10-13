<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

/**
 * Internal data model class that describes a feature flag configuration.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class FeatureFlag
{
    protected string $_key;
    protected int $_version;
    protected bool $_on = false;
    /** @var Prerequisite[] */
    protected array $_prerequisites = [];
    protected string $_salt;
    /** @var Target[] */
    protected array $_targets = [];
    /** @var Rule[] */
    protected array $_rules = [];
    protected VariationOrRollout $_fallthrough;
    protected ?int $_offVariation = null;
    protected array $_variations = [];
    protected bool $_deleted = false;
    protected bool $_trackEvents = false;
    protected bool $_trackEventsFallthrough = false;
    protected ?int $_debugEventsUntilDate = null;
    protected bool $_clientSide = false;

    // Note, trackEvents and debugEventsUntilDate are not used in EventProcessor, because
    // the PHP client doesn't do summary events. However, we need to capture them in case
    // they want to pass the flag data to the front end with allFlagsState().

    public function __construct(
        string $key,
        int $version,
        bool $on,
        array $prerequisites,
        string $salt,
        array $targets,
        array $rules,
        VariationOrRollout $fallthrough,
        ?int $offVariation,
        array $variations,
        bool $deleted,
        bool $trackEvents,
        bool $trackEventsFallthrough,
        ?int $debugEventsUntilDate,
        bool $clientSide
    ) {
        $this->_key = $key;
        $this->_version = $version;
        $this->_on = $on;
        $this->_prerequisites = $prerequisites;
        $this->_salt = $salt;
        $this->_targets = $targets;
        $this->_rules = $rules;
        $this->_fallthrough = $fallthrough;
        $this->_offVariation = $offVariation;
        $this->_variations = $variations;
        $this->_deleted = $deleted;
        $this->_trackEvents = $trackEvents;
        $this->_trackEventsFallthrough = $trackEventsFallthrough;
        $this->_debugEventsUntilDate = $debugEventsUntilDate;
        $this->_clientSide = $clientSide;
    }

    /**
     * @return \Closure
     *
     * @psalm-return \Closure(mixed):self
     */
    public static function getDecoder(): \Closure
    {
        return fn ($v) =>
            new FeatureFlag(
                $v['key'],
                $v['version'],
                $v['on'],
                array_map(Prerequisite::getDecoder(), $v['prerequisites'] ?: []),
                $v['salt'],
                array_map(Target::getDecoder(), $v['targets'] ?: []),
                array_map(Rule::getDecoder(), $v['rules'] ?: []),
                call_user_func(VariationOrRollout::getDecoder(), $v['fallthrough']),
                $v['offVariation'],
                $v['variations'] ?: [],
                $v['deleted'],
                !!($v['trackEvents'] ?? false),
                !!($v['trackEventsFallthrough'] ?? false),
                $v['debugEventsUntilDate'] ?? null,
                !!($v['clientSide'] ?? false)
            );
    }

    public static function decode(array $v): self
    {
        $decoder = FeatureFlag::getDecoder();
        return $decoder($v);
    }

    public function isClientSide(): bool
    {
        return $this->_clientSide;
    }

    public function getDebugEventsUntilDate(): ?int
    {
        return $this->_debugEventsUntilDate;
    }

    public function isDeleted(): bool
    {
        return $this->_deleted;
    }

    public function getFallthrough(): VariationOrRollout
    {
        return $this->_fallthrough;
    }

    public function getKey(): string
    {
        return $this->_key;
    }

    public function getOffVariation(): ?int
    {
        return $this->_offVariation;
    }

    public function isOn(): bool
    {
        return $this->_on;
    }

    public function getPrerequisites(): array
    {
        return $this->_prerequisites;
    }

    public function getRules(): array
    {
        return $this->_rules;
    }

    public function getSalt(): string
    {
        return $this->_salt;
    }

    public function getTargets(): array
    {
        return $this->_targets;
    }

    public function isTrackEvents(): bool
    {
        return $this->_trackEvents;
    }

    public function isTrackEventsFallthrough(): bool
    {
        return $this->_trackEventsFallthrough;
    }

    public function getVariations(): array
    {
        return $this->_variations;
    }

    public function getVersion(): int
    {
        return $this->_version;
    }
}
