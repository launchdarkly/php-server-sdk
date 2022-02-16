<?php

namespace LaunchDarkly\Impl\Events;

use LaunchDarkly\LDUser;

/**
 * Internal class that translates analytics events into the format used for sending them to LaunchDarkly.
 *
 * @ignore
 * @internal
 */
class EventSerializer
{
    /** @var bool */
    private $_allAttrsPrivate;

    /** @var array */
    private $_privateAttrNames;

    public function __construct(array $options)
    {
        $this->_allAttrsPrivate = isset($options['all_attributes_private']) && $options['all_attributes_private'];
        $this->_privateAttrNames = isset($options['private_attribute_names']) ? $options['private_attribute_names'] : [];
    }

    public function serializeEvents(array $events): string
    {
        $filtered = [];
        foreach ($events as $e) {
            $filtered[] = $this->filterEvent($e);
        }
        $ret = json_encode($filtered);
        if ($ret === false) {
            return '';
        }
        return $ret;
    }

    private function filterEvent(array $e): array
    {
        $ret = [];
        foreach ($e as $key => $value) {
            if ($key == 'user') {
                $ret[$key] = $this->serializeUser($value);
            } else {
                $ret[$key] = $value;
            }
        }
        return $ret;
    }

    private function filterAttrs(array $attrs, array &$json, ?array $userPrivateAttrs, array &$allPrivateAttrs, bool $stringify): void
    {
        foreach ($attrs as $key => $value) {
            if ($value !== null) {
                if ($this->_allAttrsPrivate ||
                    (!is_null($userPrivateAttrs) && in_array($key, $userPrivateAttrs)) ||
                    in_array($key, $this->_privateAttrNames)) {
                    $allPrivateAttrs[] = $key;
                } else {
                    $json[$key] = $stringify ? strval($value) : $value;
                }
            }
        }
    }

    private function serializeUser(LDUser $user): array
    {
        $json = ["key" => strval($user->getKey())];
        $userPrivateAttrs = $user->getPrivateAttributeNames();
        $allPrivateAttrs = [];

        $attrs = [
            'secondary' => $user->getSecondary(),
            'ip' => $user->getIP(),
            'country' => $user->getCountry(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName()
        ];
        $this->filterAttrs($attrs, $json, $userPrivateAttrs, $allPrivateAttrs, true);
        if ($user->getAnonymous()) {
            $json['anonymous'] = true;
        }
        $custom = $user->getCustom();
        if (!is_null($custom) && !empty($user->getCustom())) {
            $customOut = [];
            $this->filterAttrs($custom, $customOut, $userPrivateAttrs, $allPrivateAttrs, false);
            if ($customOut) { // if this is empty, we will return a json array for 'custom' instead of an object
                $json['custom'] = $customOut;
            }
        }
        if (count($allPrivateAttrs)) {
            sort($allPrivateAttrs);
            $json['privateAttrs'] = $allPrivateAttrs;
        }
        return $json;
    }
}
