<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\BigSegments;

use DateTimeImmutable;
use Exception;
use LaunchDarkly\BigSegmentsEvaluationStatus;
use LaunchDarkly\Impl;
use LaunchDarkly\Subsystems;
use LaunchDarkly\Types;
use Psr\Log\LoggerInterface;

class StoreManager
{
    private Types\BigSegmentsConfig $config;
    private ?Subsystems\BigSegmentsStore $store;
    private Impl\BigSegments\StoreStatusProvider $statusProvider;
    private ?Types\BigSegmentsStoreStatus $lastStatus;
    private ?DateTimeImmutable $lastStatusPollTime;

    public function __construct(Types\BigSegmentsConfig $config, private readonly LoggerInterface $logger)
    {
        $this->config = $config;
        $this->store = $config->store;
        $this->statusProvider = new Impl\BigSegments\StoreStatusProvider(
            fn () => $this->pollAndUpdateStatus(),
            $logger
        );
        $this->lastStatus = null;
        $this->lastStatusPollTime = null;
    }

    public function getStatusProvider(): Subsystems\BigSegmentStatusProvider
    {
        return $this->statusProvider;
    }

    /**
     * Retrieves the membership of a context key in a Big Segment, if a backing
     * big segments store has been configured.
     */
    public function getContextMembership(string $contextKey): ?Impl\BigSegments\MembershipResult
    {
        if ($this->store === null) {
            return null;
        }

        $cachedItem = null;
        try {
            $cachedItem = $this->config->cache?->getItem($contextKey);
        } catch (Exception $e) {
            $this->logger->warning("Failed to retrieve cached item for big segment", ['contextKey' => $contextKey, 'exception' => $e->getMessage()]);
        }
        /** @var ?array<string, bool> */
        $membership = $cachedItem?->get();

        if ($membership === null) {
            try {
                $membership = $this->store->getMembership(StoreManager::hashForContextKey($contextKey));
                if ($this->config->cache !== null && $cachedItem !== null) {
                    $cachedItem->set($membership)->expiresAfter($this->config->contextCacheTime);

                    if (!$this->config->cache->save($cachedItem)) {
                        $this->logger->warning("Failed to save Big Segment membership to cache", ['contextKey' => $contextKey]);
                    }
                }
            } catch (Exception $e) {
                $this->logger->warning("Failed to retrieve Big Segment membership", ['contextKey' => $contextKey, 'exception' => $e->getMessage()]);
                return new Impl\BigSegments\MembershipResult(null, BigSegmentsEvaluationStatus::STORE_ERROR);
            }
        }

        $nextPollingTime = ($this->lastStatusPollTime?->getTimestamp() ?? 0) + $this->config->statusPollInterval;

        $status = $this->lastStatus;
        if ($this->lastStatusPollTime === null || $nextPollingTime < time()) {
            $status = $this->pollAndUpdateStatus();
        }

        if ($status === null || !$status->isAvailable()) {
            return new Impl\BigSegments\MembershipResult($membership, BigSegmentsEvaluationStatus::STORE_ERROR);
        }

        return new Impl\BigSegments\MembershipResult($membership, $status->isStale() ? BigSegmentsEvaluationStatus::STALE : BigSegmentsEvaluationStatus::HEALTHY);
    }

    private function pollAndUpdateStatus(): Types\BigSegmentsStoreStatus
    {
        $newStatus = new Types\BigSegmentsStoreStatus(false, false);
        if ($this->store !== null) {
            try {
                $metadata = $this->store->getMetadata();
                $newStatus = new Types\BigSegmentsStoreStatus(
                    available: true,
                    stale: $metadata->isStale($this->config->staleAfter)
                );
            } catch (Exception $e) {
                $this->logger->warning("Failed to retrieve Big Segment metadata", ['exception' => $e->getMessage()]);
            }
        }

        $this->lastStatus = $newStatus;
        $this->statusProvider->updateStatus($newStatus);
        $this->lastStatusPollTime = new DateTimeImmutable();

        return $newStatus;
    }

    private static function hashForContextKey(string $contextKey): string
    {
        return base64_encode(hash('sha256', $contextKey, true));
    }
}
