<?php
namespace LaunchDarkly;

/**
 * Deprecated feature requester from an LDD-populated Redis, with APC caching.
 *
 * @deprecated Per the docs (http://php.net/manual/en/intro.apc.php):
 * "This extension (APC) is considered unmaintained and dead".
 *
 * Install APCu and use {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()} instead!
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

    protected function getCachedString($cacheKey)
    {
        if ($this->_expiration) {
            $value = \apc_fetch($cacheKey);
            return $value === false ? null : $value;
        }
        return null;
    }

    protected function putCachedString($cacheKey, $data)
    {
        if ($this->_expiration) {
            \apc_store($cacheKey, $data, $this->_expiration);
        }
    }
}
