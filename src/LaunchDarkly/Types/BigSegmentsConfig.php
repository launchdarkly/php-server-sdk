<?php

declare(strict_types=1);

namespace LaunchDarkly\Types;

use LaunchDarkly\Subsystems;
use Psr\Cache;

/**
 * Configuration options related to Big Segments.
 *
 * Big Segments are a specific type of segments. For more information, read the
 * LaunchDarkly documentation:
 * https://docs.launchdarkly.com/home/users/big-segments
 *
 * If your application uses Big Segments, you will need to create a
 * BigSegmentsConfig that at a minimum specifies what database integration to
 * use, and then pass the `BigSegmentsConfig` object as the `big_segments`
 * parameter when creating a {@see LaunchDarkly\LDClient}.
 */
class BigSegmentsConfig
{
    /** @var int Default polling interval (in seconds) */
    const DEFAULT_STATUS_POLL_INTERVAL = 5;
    /** @var int Default staleness period (in seconds) */
    const DEFAULT_STALE_AFTER = 2 * 60;

    /**
     * The frequency (in seconds) the SDK should automatically check the
     * backing store for the latest status.
     *
     * This duration is only valid for the lifetime of the request, and is
     * only useful to prevent excessive checking in long-running scripts.
     */
    public readonly int $statusPollInterval;

    /**
     * The maximum age of the metadata before it is considered stale (in
     * seconds).
     */
    public readonly int $staleAfter;

    public function __construct(
        public readonly ?Subsystems\BigSegmentsStore $store,
        public readonly ?Cache\CacheItemPoolInterface $cache = null,
        /**
         * If provided, each item inserted into the provided cache will expire
         * after this many seconds.
         *
         * If no value is provided, the cache item's `expiresAfter` method will
         * be called with null, resulting in implementation specific behavior.
         * Refer to your cache adapter's documentation for further information.
         */
        public readonly ?int $contextCacheTime = null,
        ?int $statusPollInterval = null,
        ?int $staleAfter = null
    ) {
        $this->statusPollInterval = $statusPollInterval === null || $statusPollInterval < 0 ? self::DEFAULT_STATUS_POLL_INTERVAL : $statusPollInterval;
        $this->staleAfter = $staleAfter === null || $staleAfter < 0 ? self::DEFAULT_STALE_AFTER : $staleAfter;
    }
}
