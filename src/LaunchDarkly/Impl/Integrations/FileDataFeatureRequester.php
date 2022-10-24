<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Subsystems\FeatureRequester;

/**
 * @ignore
 * @internal
 */
class FileDataFeatureRequester implements FeatureRequester
{
    private array $_filePaths;
    private array $_flags;
    private array $_segments;

    /**
     * @param array|string $filePaths
     */
    public function __construct(array|string $filePaths)
    {
        $this->_filePaths = is_array($filePaths) ? $filePaths : [$filePaths];
        $this->_flags = [];
        $this->_segments = [];
        $this->readAllData();
    }

    /**
     * Gets an individual feature flag
     *
     * @param $key string feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag
    {
        return $this->_flags[$key] ?? null;
    }

    /**
     * Gets an individual user segment
     *
     * @param $key string segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment(string $key): ?Segment
    {
        return $this->_segments[$key] ?? null;
    }

    /**
     * Gets all feature flags
     *
     * @return array<string, FeatureFlag>|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array
    {
        return $this->_flags;
    }

    private function readAllData(): void
    {
        $flags = [];
        $segments = [];
        foreach ($this->_filePaths as $filePath) {
            $this->loadFile($filePath, $flags, $segments);
        }
        $this->_flags = $flags;
        $this->_segments = $segments;
    }

    private function loadFile(string $filePath, array &$flags, array &$segments): void
    {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        if ($data == null) {
            throw new \InvalidArgumentException("File is not valid JSON: " . $filePath);
        }
        foreach ($data['flags'] ?? [] as $key => $value) {
            $flag = FeatureFlag::decode($value);
            $this->tryToAdd($flags, $key, $flag, "feature flag");
        }
        foreach ($data['flagValues'] ?? [] as $key => $value) {
            $flag = FeatureFlag::decode([
                "key" => $key,
                "version" => 1,
                "on" => false,
                "prerequisites" => [],
                "salt" => "",
                "targets" => [],
                "rules" => [],
                "fallthrough" => [],
                "offVariation" => 0,
                "variations" => [$value],
                "deleted" => false,
                "trackEvents" => false,
                "clientSide" => false
            ]);
            $this->tryToAdd($flags, $key, $flag, "feature flag");
        }
        foreach ($data['segments'] ?? [] as $key => $value) {
            $segment = Segment::decode($value);
            $this->tryToAdd($segments, $key, $segment, "user segment");
        }
    }

    private function tryToAdd(array &$array, string $key, mixed $item, string $kind): void
    {
        if (isset($array[$key])) {
            throw new \InvalidArgumentException("File data contains more than one " . $kind . " with key: " . $key);
        } else {
            $array[$key] = $item;
        }
    }
}
