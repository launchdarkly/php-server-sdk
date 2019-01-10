<?php
namespace LaunchDarkly;

use Psr\Log\LoggerInterface;

/**
 * This component allows you to use local files as a source of feature flag state. This would
 * typically be used in a test environment, to operate using a predetermined feature flag state
 * without an actual LaunchDarkly connection.
 * <p>
 * To use this component, create an instance of this class, passing the path(s) of your data
 * file(s). Then place the resulting object in your LaunchDarkly client configuration with the
 * key "feature_requester".
 * <pre>
 *     $file_data = new FileDataFeatureRequester("./testData/flags.json");
 *     $config = array("feature_requester" => $file_data, "send_events" => false);
 *     $client = new LDClient("sdk_key", $config);
 * </pre>
 * <p>
 * This will cause the client <i>not</i> to connect to LaunchDarkly to get feature flags. (Note
 * that in this example, <code>send_events</core> is also set to false so that it will not
 * connect to LaunchDarkly to send analytics events either.)
 * <p>
 */
class FileDataFeatureRequester implements FeatureRequester
{
    /** @var array  */
    private $_filePaths;
    /** @var array */
    private $_flags;
    /** @var array  */
    private $_segments;
    /** @var LoggerInterface */
    private $_logger;

    public function __construct($filePaths, $options = array())
    {
        $this->_filePaths = is_array($filePaths) ? $filePaths : array($filePaths);
        $this->_options = $options;
        $this->_flags = array();
        $this->_segments = array();
        $this->_logger = isset($options['logger']) ? $options['logger'] : null;
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
