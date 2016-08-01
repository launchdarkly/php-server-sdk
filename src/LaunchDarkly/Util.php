<?php

namespace LaunchDarkly;


use DateTime;
use DateTimeZone;

class Util {

    /**
     * @param $dateTime DateTime
     * @return int
     */
    public static function dateTimeToUnixMillis($dateTime) {
        $timeStampSeconds = (int)$dateTime->getTimeStamp();
        $timestampMicros = $dateTime->format('u');
        return $timeStampSeconds * 1000 + (int)($timestampMicros / 1000);
    }

    /**
     * @return int
     */
    public static function currentTimeUnixMillis() {
        return Util::dateTimeToUnixMillis(new DateTime('now', new DateTimeZone("UTC")));
    }


    public static function newFeatureRequestEvent($key, $user, $value, $default, $version = null, $prereqOf = null) {
        $event = array();
        $event['user'] = $user->toJSON();
        $event['value'] = $value;
        $event['kind'] = "feature";
        $event['creationDate'] = Util::currentTimeUnixMillis();
        $event['key'] = $key;
        $event['default'] = $default;
        $event['version'] = $version;
        $event['prereqOf'] = $prereqOf;
        return $event;
    }

}