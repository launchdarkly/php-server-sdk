<?php

namespace LaunchDarkly\Tests\Types;

use LaunchDarkly\Types;

class BigSegmentConfigTest extends \PHPUnit\Framework\TestCase
{
    public function testDefaultsAppropriately(): void
    {
        $config = new Types\BigSegmentConfig(store: null);

        self::assertNull($config->store);
        self::assertEquals(Types\BigSegmentConfig::DEFAULT_STATUS_POLL_INTERVAL, $config->statusPollInterval);
        self::assertEquals(Types\BigSegmentConfig::DEFAULT_STALE_AFTER, $config->staleAfter);
    }

    /**
     * @return array<array<int>>
     */
    public function nonNegativeValues(): array
    {
        return [
            [0, 0, 0],
            [100, 100, 100],
            [123, 456, 789],
        ];
    }

    /**
     * @dataProvider nonNegativeValues
     */
    public function testCanSetToNonnegativeValues(int $contextCacheTime, int $statusPollInterval, int $staleAfter): void
    {
        $config = new Types\BigSegmentConfig(
            store: null,
            contextCacheTime: $contextCacheTime,
            statusPollInterval: $statusPollInterval,
            staleAfter: $staleAfter,
        );

        self::assertNull($config->store);
        self::assertEquals($contextCacheTime, $config->contextCacheTime);
        self::assertEquals($statusPollInterval, $config->statusPollInterval);
        self::assertEquals($staleAfter, $config->staleAfter);
    }

    /**
     * @return array<array<int>>
     */
    public function negativeValues(): array
    {
        return [
            [-1, -1, -1],
            [-100, -100, -100],
            [-123, -456, -789],
        ];
    }

    /**
     * @dataProvider negativeValues
     */
    public function testNegativeValuesResetToDefaults(int $contextCacheTime, int $statusPollInterval, int $staleAfter): void
    {
        $config = new Types\BigSegmentConfig(
            store: null,
            contextCacheTime: $contextCacheTime,
            statusPollInterval: $statusPollInterval,
            staleAfter: $staleAfter,
        );

        self::assertNull($config->store);
        self::assertEquals($contextCacheTime, $config->contextCacheTime);
        self::assertEquals(Types\BigSegmentConfig::DEFAULT_STATUS_POLL_INTERVAL, $config->statusPollInterval);
        self::assertEquals(Types\BigSegmentConfig::DEFAULT_STALE_AFTER, $config->staleAfter);
    }
}
