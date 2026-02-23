<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use Kevinrob\GuzzleCache\Storage\CacheStorageInterface;
use LaunchDarkly\Impl\Integrations\GuzzleFeatureRequester;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;

class GuzzleFeatureRequesterUnitTest extends TestCase
{
    public function testConstructorAcceptsPsr6CachePool(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $requester = new GuzzleFeatureRequester('http://localhost', 'sdk-key', [
            'cache' => $pool,
            'logger' => new NullLogger(),
            'timeout' => 3,
            'connect_timeout' => 3,
        ]);

        self::assertInstanceOf(GuzzleFeatureRequester::class, $requester);
    }

    public function testConstructorAcceptsNullCache(): void
    {
        $requester = new GuzzleFeatureRequester('http://localhost', 'sdk-key', [
            'cache' => null,
            'logger' => new NullLogger(),
            'timeout' => 3,
            'connect_timeout' => 3,
        ]);

        self::assertInstanceOf(GuzzleFeatureRequester::class, $requester);
    }

    public function testConstructorAcceptsCacheStorageInterface(): void
    {
        $storage = $this->createMock(CacheStorageInterface::class);

        $requester = new GuzzleFeatureRequester('http://localhost', 'sdk-key', [
            'cache' => $storage,
            'logger' => new NullLogger(),
            'timeout' => 3,
            'connect_timeout' => 3,
        ]);

        self::assertInstanceOf(GuzzleFeatureRequester::class, $requester);
    }

    public function testConstructorAcceptsNoCacheOption(): void
    {
        $requester = new GuzzleFeatureRequester('http://localhost', 'sdk-key', [
            'logger' => new NullLogger(),
            'timeout' => 3,
            'connect_timeout' => 3,
        ]);

        self::assertInstanceOf(GuzzleFeatureRequester::class, $requester);
    }
}
