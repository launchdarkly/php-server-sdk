<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

/**
 * Internal data model class that describes a feature flag's migration settings.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class MigrationSettings
{
    public function __construct(private readonly ?int $checkRatio = null)
    {
    }

    public function getCheckRatio(): int
    {
        return $this->checkRatio ?? 1;
    }

    public static function getDecoder(): \Closure
    {
        return fn (array $v) => new MigrationSettings($v['checkRatio'] ?? null);
    }
}
