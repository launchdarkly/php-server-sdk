<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\BigSegments;

use Exception;
use LaunchDarkly\Subsystems;
use LaunchDarkly\Types;
use Psr\Log\LoggerInterface;
use SplObjectStorage;

class StoreStatusProvider implements Subsystems\BigSegmentStatusProvider
{
    private SplObjectStorage $listeners;
    /**
     * @var callable(): Types\BigSegmentStoreStatus
     */
    private $statusFn;
    private ?Types\BigSegmentStoreStatus $lastStatus;
    private LoggerInterface $logger;

    /**
     * @param callable(): Types\BigSegmentStoreStatus $statusFn
     */
    public function __construct(callable $statusFn, LoggerInterface $logger)
    {
        $this->listeners = new SplObjectStorage();
        $this->statusFn = $statusFn;
        $this->lastStatus = null;
        $this->logger = $logger;
    }

    public function attach(Subsystems\BigSegmentStatusListener $listener): void
    {
        $this->listeners->attach($listener);
    }

    public function detach(Subsystems\BigSegmentStatusListener $listener): void
    {
        $this->listeners->detach($listener);
    }

    /**
    * @internal
    */
    public function updateStatus(Types\BigSegmentStoreStatus $status): void
    {
        if ($this->lastStatus != $status) {
            $old = $this->lastStatus;
            $this->lastStatus = $status;

            $this->notify(old: $old, new: $status);
        }
    }

    private function notify(?Types\BigSegmentStoreStatus $old, Types\BigSegmentStoreStatus $new): void
    {
        /** @var Subsystems\BigSegmentStatusListener $listener */
        foreach ($this->listeners as $listener) {
            try {
                $listener->statusChanged($old, $new);
            } catch (Exception $e) {
                $this->logger->warning('A big segment status listener threw an exception', ['exception' => $e->getMessage()]);
            }
        }
    }

    public function lastStatus(): ?Types\BigSegmentStoreStatus
    {
        return $this->lastStatus;
    }

    public function status(): Types\BigSegmentStoreStatus
    {
        return ($this->statusFn)();
    }
}
