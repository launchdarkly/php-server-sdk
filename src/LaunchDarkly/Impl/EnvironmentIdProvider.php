<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl;

/**
 * @ignore
 * @internal
 *
 * Mutable holder for the LaunchDarkly environment ID associated with the SDK key.
 *
 * The HTTP feature requester populates this holder from the `X-Ld-Envid` response
 * header on successful fetches; LDClient reads from it when building hook contexts.
 * Persistent-store feature requesters never write to the holder, so the value
 * remains null for those configurations.
 */
final class EnvironmentIdProvider
{
    private ?string $_environmentId = null;

    public function get(): ?string
    {
        return $this->_environmentId;
    }

    public function set(?string $environmentId): void
    {
        if ($environmentId === null || $environmentId === '') {
            return;
        }
        $this->_environmentId = $environmentId;
    }
}
