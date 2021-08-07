<?php

namespace LaunchDarkly\Impl;

use DateTime;
use DateTimeZone;

/**
 * Internal class containing helper methods.
 *
 * @ignore
 * @internal
 */
class Util
{
    public static function dateTimeToUnixMillis(DateTime $dateTime): int
    {
        $timeStampSeconds = (int)$dateTime->getTimestamp();
        $timestampMicros = (int)$dateTime->format('u');
        return $timeStampSeconds * 1000 + (int)($timestampMicros / 1000);
    }

    public static function currentTimeUnixMillis(): int
    {
        return Util::dateTimeToUnixMillis(new DateTime('now', new DateTimeZone("UTC")));
    }

    public static function isHttpErrorRecoverable(int $status): bool
    {
        if ($status >= 400 && $status < 500) {
            return ($status == 400) || ($status == 408) || ($status == 429);
        }
        return true;
    }

    public static function httpErrorMessage(int $status, string $context, string $retryMessage): string
    {
        return 'Received error ' . $status
            . (($status == 401) ? ' (invalid SDK key)' : '')
            . ' for ' . $context . ' - '
            . (Util::isHttpErrorRecoverable($status) ? $retryMessage : 'giving up permanently');
    }
}
