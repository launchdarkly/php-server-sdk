<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use Beste\Cache\InMemoryCache;
use Beste\Clock\FrozenClock;
use LaunchDarkly\Impl\Integrations\Psr6FeatureRequesterCache;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class Psr6FeatureRequesterCacheTest extends TestCase
{
    public function testGetReturnsNullOnMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);

        $cache = new Psr6FeatureRequesterCache($pool);
        self::assertNull($cache->getCachedString('some-key'));
    }

    public function testGetReturnsValueOnHit(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn('cached-value');

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);

        $cache = new Psr6FeatureRequesterCache($pool);
        self::assertEquals('cached-value', $cache->getCachedString('some-key'));
    }

    public function testPutStoresValueWithNullTtlByDefault(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('set')->with('the-data');
        $item->expects(self::once())->method('expiresAfter')->with(null);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects(self::once())->method('save')->with($item);

        $cache = new Psr6FeatureRequesterCache($pool);
        $cache->putCachedString('some-key', 'the-data');
    }

    public function testPutStoresValueWithExplicitTtl(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('set')->with('the-data');
        $item->expects(self::once())->method('expiresAfter')->with(60);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects(self::once())->method('save')->with($item);

        $cache = new Psr6FeatureRequesterCache($pool, 60);
        $cache->putCachedString('some-key', 'the-data');
    }

    public function testPutStoresNullValue(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('set')->with(null);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects(self::once())->method('save')->with($item);

        $cache = new Psr6FeatureRequesterCache($pool);
        $cache->putCachedString('some-key', null);
    }

    public function testKeySanitizationEncodesUnsafeCharacters(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $receivedKeys = [];
        $pool->method('getItem')->willReturnCallback(function (string $key) use (&$receivedKeys) {
            $receivedKeys[] = $key;
            $item = $this->createMock(CacheItemInterface::class);
            $item->method('isHit')->willReturn(false);
            return $item;
        });

        $cache = new Psr6FeatureRequesterCache($pool);

        // "launchdarkly:features:$all" contains ":" and "$" which are unsafe
        $cache->getCachedString('launchdarkly:features:$all');

        self::assertCount(1, $receivedKeys);
        // ":" → "_3a", "$" → "_24"
        self::assertEquals('launchdarkly_3afeatures_3a_24all', $receivedKeys[0]);
    }

    public function testKeySanitizationPreservesAlphanumericAndDot(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $receivedKey = null;
        $pool->method('getItem')->willReturnCallback(function (string $key) use (&$receivedKey) {
            $receivedKey = $key;
            $item = $this->createMock(CacheItemInterface::class);
            $item->method('isHit')->willReturn(false);
            return $item;
        });

        $cache = new Psr6FeatureRequesterCache($pool);
        $cache->getCachedString('safe.key123');

        // Alphanumeric and dot are preserved as-is
        self::assertEquals('safe.key123', $receivedKey);
    }

    public function testKeySanitizationEncodesUnderscores(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $receivedKey = null;
        $pool->method('getItem')->willReturnCallback(function (string $key) use (&$receivedKey) {
            $receivedKey = $key;
            $item = $this->createMock(CacheItemInterface::class);
            $item->method('isHit')->willReturn(false);
            return $item;
        });

        $cache = new Psr6FeatureRequesterCache($pool);
        $cache->getCachedString('my_key');

        // Underscores are encoded because "_" is the escape prefix
        self::assertEquals('my_5fkey', $receivedKey);
    }

    public function testKeySanitizationAvoidCollisions(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $receivedKeys = [];
        $pool->method('getItem')->willReturnCallback(function (string $key) use (&$receivedKeys) {
            $receivedKeys[] = $key;
            $item = $this->createMock(CacheItemInterface::class);
            $item->method('isHit')->willReturn(false);
            return $item;
        });

        $cache = new Psr6FeatureRequesterCache($pool);

        // These two keys must not collide after sanitization
        $cache->getCachedString('my-flag');
        $cache->getCachedString('my_flag');

        self::assertCount(2, $receivedKeys);
        // "my-flag" → "my_2dflag" (hyphen encoded)
        // "my_flag" → "my_5fflag" (underscore encoded)
        self::assertEquals('my_2dflag', $receivedKeys[0]);
        self::assertEquals('my_5fflag', $receivedKeys[1]);
        self::assertNotEquals($receivedKeys[0], $receivedKeys[1]);
    }

    // --- TTL expiration tests using InMemoryCache + FrozenClock ---

    public function testCachedItemPersistsWithoutTtl(): void
    {
        $clock = FrozenClock::fromUTC();
        $pool = new InMemoryCache($clock);
        $cache = new Psr6FeatureRequesterCache($pool);

        $cache->putCachedString('some-key', 'the-data');

        // Advance time significantly — item should still be present
        $clock->setTo($clock->now()->add(new \DateInterval('P1D')));

        self::assertEquals('the-data', $cache->getCachedString('some-key'));
    }

    public function testCachedItemSurvivesBeforeTtlExpires(): void
    {
        $clock = FrozenClock::fromUTC();
        $pool = new InMemoryCache($clock);
        $cache = new Psr6FeatureRequesterCache($pool, 300);

        $cache->putCachedString('some-key', 'the-data');

        // Advance 2 minutes — within the 5-minute TTL
        $clock->setTo($clock->now()->add(new \DateInterval('PT2M')));

        self::assertEquals('the-data', $cache->getCachedString('some-key'));
    }

    public function testCachedItemExpiresAfterTtl(): void
    {
        $clock = FrozenClock::fromUTC();
        $pool = new InMemoryCache($clock);
        $cache = new Psr6FeatureRequesterCache($pool, 300);

        $cache->putCachedString('some-key', 'the-data');

        // Advance past the 5-minute TTL
        $clock->setTo($clock->now()->add(new \DateInterval('PT301S')));

        self::assertNull($cache->getCachedString('some-key'));
    }
}
