<?php
namespace LaunchDarkly\Impl\Integrations;

use LaunchDarkly\FeatureFlag;
use LaunchDarkly\FeatureRequester;
use LaunchDarkly\Segment;

class FileDataFeatureRequester implements FeatureRequester
{
    /** @var array  */
    private $_filePaths;
    /** @var array */
    private $_flags;
    /** @var array  */
    private $_segments;

    public function __construct($filePaths)
    {
        $this->_filePaths = is_array($filePaths) ? $filePaths : array($filePaths);
        $this->_flags = array();
        $this->_segments = array();
        $this->readAllData();
    }

    /**
     * Gets an individual feature flag
     *
     * @param $key string feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature($key)
    {
        return isset($this->_flags[$key]) ? $this->_flags[$key] : null;
    }

    /**
     * Gets an individual user segment
     *
     * @param $key string segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment($key)
    {
        return isset($this->_segments[$key]) ? $this->_segments[$key] : null;
    }

    /**
     * Gets all feature flags
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures()
    {
        return $this->_flags;
    }

    private function readAllData()
    {
        $flags = array();
        $segments = array();
        foreach ($this->_filePaths as $filePath) {
            $this->loadFile($filePath, $flags, $segments);
        }
        $this->_flags = $flags;
        $this->_segments = $segments;
    }

    private function loadFile($filePath, &$flags, &$segments)
    {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        if ($data == null) {
            throw new \InvalidArgumentException("File is not valid JSON: " + $filePath);
        }
        if (isset($data['flags'])) {
            foreach ($data['flags'] as $key => $value) {
                $flag = FeatureFlag::decode($value);
                $this->tryToAdd($flags, $key, $flag, "feature flag");
            }
        }
        if (isset($data['flagValues'])) {
            foreach ($data['flagValues'] as $key => $value) {
                $flag = FeatureFlag::decode(array(
                    "key" => $key,
                    "version" => 1,
                    "on" => false,
                    "prerequisites" => array(),
                    "salt" => "",
                    "targets" => array(),
                    "rules" => array(),
                    "fallthrough" => array(),
                    "offVariation" => 0,
                    "variations" => array($value),
                    "deleted" => false,
                    "trackEvents" => false,
                    "clientSide" => false
                ));
                $this->tryToAdd($flags, $key, $flag, "feature flag");
            }
        }
        if (isset($data['segments'])) {
            foreach ($data['segments'] as $key => $value) {
                $segment = Segment::decode($value);
                $this->tryToAdd($segments, $key, $segment, "user segment");
            }
        }
    }

    private function tryToAdd(&$array, $key, $item, $kind)
    {
        if (isset($array[$key])) {
            throw new \InvalidArgumentException("File data contains more than one " . $kind . " with key: " . $key);
        } else {
            $array[$key] = $item;
        }
    }
}
