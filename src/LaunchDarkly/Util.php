<?php

namespace LaunchDarkly;

use DateTime;
use DateTimeZone;

class Util
{
    /**
     * @param $dateTime DateTime
     * @return int
     */
    public static function dateTimeToUnixMillis($dateTime)
    {
        $timeStampSeconds = (int)$dateTime->getTimestamp();
        $timestampMicros = $dateTime->format('u');
        return $timeStampSeconds * 1000 + (int)($timestampMicros / 1000);
    }

    /**
     * @return int
     */
    public static function currentTimeUnixMillis()
    {
        return Util::dateTimeToUnixMillis(new DateTime('now', new DateTimeZone("UTC")));
    }

    /**
     * @param $status int
     * @return boolean
     */
    public static function isHttpErrorRecoverable($status)
    {
        if ($status >= 400 && $status < 500) {
            return ($status == 400) || ($status == 408) || ($status == 429);
        }
        return true;
    }

    /**
     * @param $status int
     * @param $context string
     * @param $retryMessage string
     * @return string
     */
    public static function httpErrorMessage($status, $context, $retryMessage)
    {
        return 'Received error ' . $status
            . (($status == 401) ? ' (invalid SDK key)' : '')
            . ' for ' . $context . ' - '
            . (Util::isHttpErrorRecoverable($status) ? $retryMessage : 'giving up permanently');
    }
}
