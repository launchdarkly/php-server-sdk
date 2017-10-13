<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class EventSerializer {

    private $_allAttrsPrivate;
    private $_privateAttrNames;

    public function __construct($options) {
        $this->_allAttrsPrivate = isset($options['allAttrsPrivate']) && $options['allAttrsPrivate'];
        $this->_privateAttrNames = isset($options['privateAttrNames']) ? $options['privateAttrNames'] : array();
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

    private function isPrivateAttr($name, $userPrivateAttrs) {
        return ($this->_allAttrsPrivate ||
                array_search($name, $userPrivateAttrs) !== FALSE ||
                array_search($name, $this->_privateAttrNames) !== FALSE);
    }

    private function serializeUser($user) {
        $json = array("key" => $user->getKey());
        $userPrivateAttrs = $user->getPrivateAttrs();
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
        foreach ($attrs as $key => $value) {
            if ($value != null) {
                if ($this->isPrivateAttr($key, $userPrivateAttrs)) {
                    $allPrivateAttrs[$key] = $key;
                }
                else {
                    $json[$key] = $value;
                }
            }
        }
        if (!empty($user->getCustom())) {
            $customs = array();
            foreach ($user->getCustom() as $key => $value) {
                if ($value != null) {
                    if ($this->isPrivateAttr($key, $userPrivateAttrs)) {
                        $allPrivateAttrs[$key] = $key;
                    }
                    else {
                        $customs[$key] = $value;
                    }
                }
            }
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
