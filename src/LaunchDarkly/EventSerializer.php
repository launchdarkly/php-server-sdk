<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class EventSerializer {

    private $_allAttrsPrivate;
    private $_privateAttrNames;

    public function __construct($options) {
        $this->_allAttrsPrivate = isset($options['allAttributesPrivate']) && $options['allAttributesPrivate'];
        $this->_privateAttrNames = isset($options['privateAttributeNames']) ? $options['privateAttributeNames'] : array();
    }

    public function serializeEvents($events) {
        $filtered = array();
        foreach ($events as $e) {
            array_push($filtered, $this->filterEvent($e));
        }
        return json_encode($filtered);
    }

    private function filterEvent($e) {
        $ret = array();
        foreach ($e as $key => $value) {
            if ($key == 'user') {
                $ret[$key] = $this->serializeUser($value);
            }
            else {
                $ret[$key] = $value;
            }
        }
        return $ret;
    }

    private function filterAttrs($attrs, &$json, $userPrivateAttrs, &$allPrivateAttrs) {
        foreach ($attrs as $key => $value) {
            if ($value != null) {
                if ($this->_allAttrsPrivate ||
                    array_search($key, $userPrivateAttrs) !== FALSE ||
                    array_search($key, $this->_privateAttrNames) !== FALSE) {
                    $allPrivateAttrs[$key] = true;
                }
                else {
                    $json[$key] = $value;
                }
            }
        }
    }

    private function serializeUser($user) {
        $json = array("key" => $user->getKey());
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
            'lastName' => $user->getLastName(),
            'anonymous' => $user->getAnonymous()
        );
        $this->filterAttrs($attrs, $json, $userPrivateAttrs, $allPrivateAttrs);
        if (!empty($user->getCustom())) {
            $customs = array();
            $this->filterAttrs($user->getCustom(), $customs, $userPrivateAttrs, $allPrivateAttrs);
            $json['custom'] = $customs;
        }
        if (count($allPrivateAttrs)) {
            $pa = array_keys($allPrivateAttrs);
            sort($pa);
            $json['privateAttrs'] = $pa;
        }
        return $json;
    }
}
