<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

/**
 * Internal data model class that describes a feature flag rule.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class Rule extends VariationOrRollout
{
    private ?string $_id = null;
    /** @var Clause[] */
    private array $_clauses = [];
    private bool $_trackEvents;

    public function __construct(
        ?int $variation,
        ?Rollout $rollout,
        ?string $id,
        array $clauses,
        bool $trackEvents
    ) {
        parent::__construct($variation, $rollout);
        $this->_id = $id;
        $this->_clauses = $clauses;
        $this->_trackEvents = $trackEvents;
    }

    public static function getDecoder(): \Closure
    {
        return fn (array $v) =>
            new Rule(
                $v['variation'] ?? null,
                isset($v['rollout']) ? call_user_func(Rollout::getDecoder(), $v['rollout']) : null,
                $v['id'] ?? null,
                array_map(Clause::getDecoder(), $v['clauses']),
                !!($v['trackEvents'] ?? false)
            );
    }

    public function getId(): ?string
    {
        return $this->_id;
    }
    
    /**
     * @return Clause[]
     */
    public function getClauses(): array
    {
        return $this->_clauses;
    }

    public function isTrackEvents(): bool
    {
        return $this->_trackEvents;
    }
}
