<?php

namespace LaunchDarkly\Tests\Impl\BigSegments;

use LaunchDarkly\Impl\BigSegments;
use LaunchDarkly\Subsystems\BigSegmentStatusListener;
use LaunchDarkly\Types;
use LaunchDarkly\Types\BigSegmentsStoreStatus;

class StoreStatusProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testStatusDelegatesToStatusFn(): void
    {
        $statuses = [
            new Types\BigSegmentsStoreStatus(true, true),
            new Types\BigSegmentsStoreStatus(true, false),
            new Types\BigSegmentsStoreStatus(false, true),
            new Types\BigSegmentsStoreStatus(false, false),
        ];

        $provider = new BigSegments\StoreStatusProvider(
            function () use (&$statuses): Types\BigSegmentsStoreStatus {
                return array_shift($statuses);
            },
            new \Psr\Log\NullLogger()
        );

        $status = $provider->status();
        $this->assertTrue($status->isAvailable());
        $this->assertTrue($status->isStale());

        $status = $provider->status();
        $this->assertTrue($status->isAvailable());
        $this->assertFalse($status->isStale());

        $status = $provider->status();
        $this->assertFalse($status->isAvailable());
        $this->assertTrue($status->isStale());

        $status = $provider->status();
        $this->assertFalse($status->isAvailable());
        $this->assertFalse($status->isStale());
    }

    public function testListenersAreNotifiedWhenStatusIsChanged(): void
    {
        $provider = new BigSegments\StoreStatusProvider(
            function (): Types\BigSegmentsStoreStatus {
                return new Types\BigSegmentsStoreStatus(true, false);
            },
            new \Psr\Log\NullLogger()
        );

        $firstListener = new SimpleListener();
        $secondListener = new SimpleListener();

        $provider->attach($firstListener);
        $provider->attach($secondListener);

        $provider->detach($firstListener);
        $provider->updateStatus(new Types\BigSegmentsStoreStatus(true, true));

        $this->assertNull($firstListener->old);
        $this->assertEquals(0, $firstListener->callCount);

        $this->assertNull($secondListener->old);
        $this->assertTrue($secondListener->new->isAvailable());
        $this->assertTrue($secondListener->new->isStale());
        $this->assertEquals(1, $secondListener->callCount);
    }

    public function testListenersIgnoredIfStatusDoesNotChange(): void
    {
        $provider = new BigSegments\StoreStatusProvider(
            function (): Types\BigSegmentsStoreStatus {
                return new Types\BigSegmentsStoreStatus(true, false);
            },
            new \Psr\Log\NullLogger()
        );

        $listener = new SimpleListener();
        $provider->attach($listener);

        $provider->updateStatus(new Types\BigSegmentsStoreStatus(true, false));
        $this->assertEquals(1, $listener->callCount);

        $provider->updateStatus(new Types\BigSegmentsStoreStatus(true, false));
        $this->assertEquals(1, $listener->callCount);
    }

    public function testExceptionsInListenersDoNotHaltExecution(): void
    {
        $provider = new BigSegments\StoreStatusProvider(
            function (): Types\BigSegmentsStoreStatus {
                return new Types\BigSegmentsStoreStatus(true, false);
            },
            new \Psr\Log\NullLogger()
        );

        $firstListener = new ExceptionListener();
        $secondListener = new SimpleListener();
        $provider->attach($firstListener);
        $provider->attach($secondListener);

        $provider->updateStatus(new Types\BigSegmentsStoreStatus(true, false));

        $this->assertEquals(1, $firstListener->callCount);

        $this->assertNull($secondListener->old);
        $this->assertTrue($secondListener->new->isAvailable());
        $this->assertFalse($secondListener->new->isStale());
        $this->assertEquals(1, $secondListener->callCount);
    }
}

class SimpleListener implements BigSegmentStatusListener
{
    public ?BigSegmentsStoreStatus $old = null;
    public ?BigSegmentsStoreStatus $new = null;
    public int $callCount = 0;

    public function statusChanged(?BigSegmentsStoreStatus $old, BigSegmentsStoreStatus $new): void
    {
        $this->callCount++;
        $this->old = $old;
        $this->new = $new;
    }
}

class ExceptionListener implements BigSegmentStatusListener
{
    public int $callCount = 0;

    public function statusChanged(?BigSegmentsStoreStatus $old, BigSegmentsStoreStatus $new): void
    {
        $this->callCount++;
        throw new \Exception("test exception");
    }
}
