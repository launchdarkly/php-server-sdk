<?php
namespace LaunchDarkly;

/**
 * Feature requester from an LDD-populated redis, with APC caching
 * @deprecated Per the docs (http://php.net/manual/en/intro.apc.php):
 * "This extension (APC) is considered unmaintained and dead".
 * Install APCu and use \LaunchDarkly\ApcuLDDFeatureRequester instead!
 *
 * @package LaunchDarkly
 */
class ApcLDDFeatureRequester extends LDDFeatureRequester
{
    protected $_expiration = 30;

    public function __construct($baseUri, $sdkKey, $options)
    {
        parent::__construct($baseUri, $sdkKey, $options);

        if (isset($options['apc_expiration'])) {
            $this->_expiration = (int)$options['apc_expiration'];
        }
    }

    /**
     * @param $key
     * @param $success
     * @return mixed
     */
    protected function fetch($key, &$success = null)
    {
        return \apc_fetch($key, $success);
    }

    protected function get_from_cache($key)
    {
        $key = self::make_cache_key($key);
        $enabled = $this->fetch($key);
        if ($enabled === false) {
            return null;
        } else {
            return $enabled;
        }
    }

    /**
     * @param $key
     * @param $var
     * @param int $ttl
     * @return mixed
     */
    protected function add($key, $var, $ttl = 0)
    {
        return \apc_add($key, $var, $ttl);
    }

    protected function store_in_cache($key, $val)
    {
        $this->add($this->make_cache_key($key), $val, $this->_expiration);
    }

    private function make_cache_key($name)
    {
        return $this->_features_key.'.'.$name;
    }
}
