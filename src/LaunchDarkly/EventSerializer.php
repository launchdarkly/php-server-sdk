<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class EventSerializer
{
    private $_allAttrsPrivate;
    private $_privateAttrNames;

    public function __construct($options)
    {
        $this->_allAttrsPrivate = isset($options['all_attributes_private']) && $options['all_attributes_private'];
        $this->_privateAttrNames = isset($options['private_attribute_names']) ? $options['private_attribute_names'] : array();
    }

    public function serializeEvents($events)
    {
        $filtered = array();
        foreach ($events as $e) {
            array_push($filtered, $this->filterEvent($e));
        }
        return json_encode($filtered);
    }

    private function filterEvent($e)
    {
        $ret = array();
        foreach ($e as $key => $value) {
            if ($key == 'user') {
                $ret[$key] = $this->serializeUser($value);
            } else {
                $ret[$key] = $value;
            }
        }
        return $ret;
    }

    private function filterAttrs($attrs, &$json, $userPrivateAttrs, &$allPrivateAttrs, $stringify)
    {
        foreach ($attrs as $key => $value) {
            if ($value != null) {
                if ($this->_allAttrsPrivate ||
                    array_search($key, $userPrivateAttrs) !== false ||
                    array_search($key, $this->_privateAttrNames) !== false) {
                    $allPrivateAttrs[$key] = true;
                } else {
                    $json[$key] = $stringify ? strval($value) : $value;
                }
            }
        }
    }

    private function serializeUser($user)
    {
        $json = array("key" => strval($user->getKey()));
        $userPrivateAttrs = $user->getPrivateAttributeNames();
        $allPrivateAttrs = array();

        $attrs = array(
            'secondary' => $user->getSecondary(),
            'ip' => $user->getIP(),
            'country' => $user->getCountry(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName()
        );
        $this->filterAttrs($attrs, $json, $userPrivateAttrs, $allPrivateAttrs, true);
        if ($user->getAnonymous()) {
            $json['anonymous'] = true;
        }
        if (!empty($user->getCustom())) {
            $customs = array();
            $this->filterAttrs($user->getCustom(), $customs, $userPrivateAttrs, $allPrivateAttrs, false);
            if ($customs) { // if this is empty, we will return a json array for 'custom' instead of an object
                $json['custom'] = $customs;
            }
        }
        if (count($allPrivateAttrs)) {
            $pa = array_keys($allPrivateAttrs);
            sort($pa);
            $json['privateAttrs'] = $pa;
        }
        return $json;
    }
}
