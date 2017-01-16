<?php
namespace LaunchDarkly;


/**
 * Feature requester from an LDD-populated redis, with APC caching
 *
 * @package LaunchDarkly
 */
class ApcuLDDFeatureRequester extends ApcLDDFeatureRequester
{
    /**
     * @param $key
     * @param null $success
     * @return mixed
     */
    protected function fetch($key, &$success = null)
    {
        return \apcu_fetch($key, $success);
    }

    /**
     * @param $key
     * @param $var
     * @param int $ttl
     * @return bool
     */
    protected function add($key, $var, $ttl = 0)
    {
        return \apcu_add($key, $var, $ttl);
    }
}