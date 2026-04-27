<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Impl;

use LaunchDarkly\Impl\EnvironmentIdProvider;
use PHPUnit\Framework\TestCase;

class EnvironmentIdProviderTest extends TestCase
{
    public function testStartsNull(): void
    {
        $provider = new EnvironmentIdProvider();
        $this->assertNull($provider->get());
    }

    public function testSetStoresValue(): void
    {
        $provider = new EnvironmentIdProvider();
        $provider->set('env-abc');
        $this->assertSame('env-abc', $provider->get());
    }

    public function testSetIgnoresNull(): void
    {
        $provider = new EnvironmentIdProvider();
        $provider->set('env-abc');
        $provider->set(null);
        $this->assertSame('env-abc', $provider->get());
    }

    public function testSetIgnoresEmptyString(): void
    {
        $provider = new EnvironmentIdProvider();
        $provider->set('env-abc');
        $provider->set('');
        $this->assertSame('env-abc', $provider->get());
    }

    public function testSetOverwritesPriorValue(): void
    {
        $provider = new EnvironmentIdProvider();
        $provider->set('first');
        $provider->set('second');
        $this->assertSame('second', $provider->get());
    }
}
