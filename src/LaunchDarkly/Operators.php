<?php
namespace LaunchDarkly;

use DateTime;
use Exception;

class Operators {
    const RFC3339 = 'Y-m-d\TH:i:s.uP';

    /**
     * @param $op string
     * @param $u
     * @param $c
     * @return bool
     */
    public static function apply($op, $u, $c) {
        try {
            if ($u == null || $c == null) {
                return false;
            }
            switch ($op) {
                case "in":
                    if ($u == $c) {
                        return true;
                    }
                    break;
                case "endsWith":
                    if (is_string($u) && is_string($c)) {
                        return substr_compare($u, $c, strlen($c)) === 0;
                    }
                    break;
                case "startsWith":
                    if (is_string($u) && is_string($c)) {
                        return substr_compare($u, $c, -strlen($c)) === 0;
                    }
                    break;
                case "matches":
                    if (is_string($u) && is_string($c)) {
                        return preg_match($c, $u) == 1;
                    }
                    break;
                case "contains":
                    if (is_string($u) && is_string($c)) {
                        return strpos($u, $c) !== FALSE;
                    }
                    break;
                case "lessThan":
                    if (is_numeric($u) && is_numeric($c)) {
                        return $u < $c;
                    }
                    break;
                case "lessThanOrEqual":
                    if (is_numeric($u) && is_numeric($c)) {
                        return $u <= $c;
                    }
                    break;
                case "greaterThan":
                    if (is_numeric($u) && is_numeric($c)) {
                        return $u > $c;
                    }
                    break;
                case "greaterThanOrEqual":
                    if (is_numeric($u) && is_numeric($c)) {
                        return $u >= $c;
                    }
                    break;
                case "before":
                    $uTime = self::parseTime($u);
                    if ($uTime != null) {
                        $cTime = self::parseTime($c);
                        if ($cTime != null) {
                            return $uTime < $cTime;
                        }
                    }
                    break;
                case "after":
                    $uTime = self::parseTime($u);
                    if ($uTime != null) {
                        $cTime = self::parseTime($c);
                        if ($cTime != null) {
                            return $uTime > $cTime;
                        }
                    }
                    break;
            }
        } finally {
            return false;
        }
    }

    /**
     * @param $in
     * @return null|int|float
     */
    public static function parseTime($in) {
        if (is_numeric($in)) {
            return $in;
        }

        if ($in instanceof DateTime) {
            return self::dateTimeToUnixMillis($in);
        }

        if (is_string($in)) {
            try {
                $dateTime = new DateTime($in);
                return self::dateTimeToUnixMillis($dateTime);
            } catch (Exception $e) {
                error_log("LaunchDarkly: Could not parse timestamp: " . $in);
                return null;
            }
        }
        return null;
    }

    /**
     * @param $dateTime DateTime
     * @return int
     */
    private static function dateTimeToUnixMillis($dateTime) {
        $timeStampSeconds = (int)$dateTime->getTimeStamp();
        $timestampMicros = $dateTime->format('u');
        return $timeStampSeconds * 1000 + (int)($timestampMicros / 1000);
    }

}