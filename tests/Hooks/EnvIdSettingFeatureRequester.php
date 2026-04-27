<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Hooks;

use LaunchDarkly\Impl\EnvironmentIdProvider;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Subsystems\FeatureRequester;
use LaunchDarkly\Tests\ModelBuilders;

/**
 * Test feature requester that simulates GuzzleFeatureRequester's behavior: each fetch
 * writes a configured value into the env ID holder. Used by hook tests that exercise
 * the env-ID-on-EvaluationSeriesContext plumbing.
 */
class EnvIdSettingFeatureRequester implements FeatureRequester
{
    private ?EnvironmentIdProvider $provider;

    public function __construct(array $options, private readonly string $envId)
    {
        $envIdProvider = $options['_environment_id_provider'] ?? null;
        $this->provider = $envIdProvider instanceof EnvironmentIdProvider ? $envIdProvider : null;
    }

    public function getFeature(string $key): ?FeatureFlag
    {
        $this->provider?->set($this->envId);
        return ModelBuilders::flagBuilder($key)
            ->version(1)
            ->on(false)
            ->variations('v')
            ->offVariation(0)
            ->fallthroughVariation(0)
            ->build();
    }

    public function getSegment(string $key): ?Segment
    {
        $this->provider?->set($this->envId);
        return null;
    }

    public function getAllFeatures(): ?array
    {
        $this->provider?->set($this->envId);
        return [];
    }
}
