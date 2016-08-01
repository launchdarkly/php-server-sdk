<?php

namespace LaunchDarkly;


use DateTime;

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

}