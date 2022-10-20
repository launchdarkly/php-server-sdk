<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

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
    protected array $_included;
    /** @var string[] */
    protected array $_excluded;
    /** @var SegmentTarget[] */
    protected array $_includedContexts;
    /** @var SegmentTarget[] */
    protected array $_excludedContexts;
    protected string $_salt;
    /** @var SegmentRule[] */
    protected array $_rules = [];
    protected bool $_deleted = false;

    public function __construct(
        string $key,
        int $version,
        array $included,
        array $excluded,
        array $includedContexts,
        array $excludedContexts,
        string $salt,
        array $rules,
        bool $deleted
    ) {
        $this->_key = $key;
        $this->_version = $version;
        $this->_included = $included;
        $this->_excluded = $excluded;
        $this->_includedContexts = $includedContexts;
        $this->_excludedContexts = $excludedContexts;
        $this->_salt = $salt;
        $this->_rules = $rules;
        $this->_deleted = $deleted;
    }

    public static function getDecoder(): \Closure
    {
        return fn (array $v) =>
            new Segment(
                $v['key'],
                $v['version'],
                $v['included'] ?: [],
                $v['excluded'] ?: [],
                array_map(SegmentTarget::getDecoder(), $v['includedContexts'] ?? []),
                array_map(SegmentTarget::getDecoder(), $v['excludedContexts'] ?? []),
                $v['salt'],
                array_map(SegmentRule::getDecoder(), $v['rules'] ?: []),
                $v['deleted']
            );
    }

    public static function decode(array $v): Segment
    {
        return static::getDecoder()($v);
    }

    public function isDeleted(): bool
    {
        return $this->_deleted;
    }

    /** @return string[] */
    public function getExcluded(): array
    {
        return $this->_excluded;
    }

    /** @return SegmentTarget[] */
    public function getExcludedContexts(): array
    {
        return $this->_excludedContexts;
    }

    /** @return string[] */
    public function getIncluded(): array
    {
        return $this->_included;
    }

    /** @return SegmentTarget[] */
    public function getIncludedContexts(): array
    {
        return $this->_includedContexts;
    }

    public function getKey(): string
    {
        return $this->_key;
    }

    /** @return SegmentRule[] */
    public function getRules(): array
    {
        return $this->_rules;
    }

    public function getSalt(): string
    {
        return $this->_salt;
    }

    public function getVersion(): ?int
    {
        return $this->_version;
    }
}
