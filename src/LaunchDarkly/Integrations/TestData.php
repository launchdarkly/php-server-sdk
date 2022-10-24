<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Integrations\TestData\FlagBuilder;
use LaunchDarkly\Subsystems\FeatureRequester;

class TestData implements FeatureRequester
{
    protected array $_flagBuilders;
    protected array $_currentFlags;

    public function __construct()
    {
        $this->_flagBuilders = [];
        $this->_currentFlags = [];
    }

    /**
     * Gets the configuration for a specific feature flag.
     *
     * @param string $key feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag
    {
        return $this->_currentFlags[$key] ?? null;
    }

    /**
     * Gets the configuration for a specific user segment.
     *
     * @param string $key segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment(string $key): ?Segment
    {
        return null;
    }

    /**
     * Gets all feature flags.
     *
     * @return array<string, FeatureFlag>|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array
    {
        return $this->_currentFlags;
    }

    /**
     * Creates a new instance of the test data source
     *
     * @return TestData a new configurable test data source
     */
    public function dataSource(): TestData
    {
        return new TestData();
    }

    /**
     * Creates or copies a `FlagBuilder` for building a test flag configuration.
     *
     * If this flag key has already been defined in this `TestData` instance, then the builder
     * starts with the same configuration that was last provided for this flag.
     *
     * Otherwise, it starts with a new default configuration in which the flag has `true` and
     * `false` variations, is `true` for all users when targeting is turned on and
     * `false` otherwise, and currently has targeting turned on. You can change any of those
     * properties, and provide more complex behavior, using the `FlagBuilder` methods.
     *
     * Once you have set the desired configuration, pass the builder to `update`.
     *
     * @param string $key the flag key
     * @return FlagBuilder the flag configuration builder object
     */
    public function flag(string $key): FlagBuilder
    {
        if (isset($this->_flagBuilders[$key])) {
            return $this->_flagBuilders[$key]->copy();
        } else {
            $flagBuilder = new FlagBuilder($key);
            return $flagBuilder->booleanFlag();
        }
    }

    /**
     * Updates the test data with the specified flag configuration.
     *
     * This has the same effect as if a flag were added or modified on the LaunchDarkly dashboard.
     * It immediately propagates the flag change to any `LDClient` instance(s) that you have
     * already configured to use this `TestData`. If no `LDClient` has been started yet,
     * it simply adds this flag to the test data which will be provided to any `LDClient` that
     * you subsequently configure.
     *
     * Any subsequent changes to this `FlagBuilder` instance do not affect the test data,
     * unless you call `update(FlagBuilder)` again.
     *
     * @param FlagBuilder $flagBuilder a flag configuration builder
     * @return TestData the same `TestData` instance
     */
    public function update(FlagBuilder $flagBuilder): TestData
    {
        $key = $flagBuilder->getKey();
        $oldVersion = 0;

        $oldFlag = $this->_currentFlags[$key] ?? null;
        if ($oldFlag) {
            $oldVersion = $oldFlag->getVersion();
        }

        $newFlag = $flagBuilder->build($oldVersion + 1);
        $newFeatureFlag = FeatureFlag::decode($newFlag);
        $this->_currentFlags[$key] = $newFeatureFlag;
        $this->_flagBuilders[$key] = $flagBuilder->copy();
        return $this;
    }
}
