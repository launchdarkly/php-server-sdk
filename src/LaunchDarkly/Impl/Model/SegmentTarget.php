<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

/**
 * Internal data model class that describes a segment targeting list.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class SegmentTarget
{
    private ?string $_contextKind;
    /** @var string[] */
    private array $_values;

    public function __construct(?string $contextKind, array $values)
    {
        $this->_contextKind = $contextKind;
        $this->_values = $values;
    }

    public static function getDecoder(): \Closure
    {
        return fn (array $v) => new SegmentTarget($v['contextKind'] ?? null, $v['values']);
    }

    public function getContextKind(): ?string
    {
        return $this->_contextKind;
    }

    /**
     * @return \string[]
     */
    public function getValues(): array
    {
        return $this->_values;
    }
}
