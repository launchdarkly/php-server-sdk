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
        error_log("apply with op: $op u: $u c: $c");
        try {
            if ($u === null || $c === null) {
                error_log("one or both are null");
                return false;
            }
            switch ($op) {
                case "in":
                    error_log("in with u: $u and c: $c");
                    if ($u === $c) {
                        error_log("returning true from in op");
                        return true;
                    }
                    if (is_numeric($u) && is_numeric($c)) {
                        return $u == $c;
                    }
                    break;
                case "endsWith":
                    if (is_string($u) && is_string($c)) {
                        return $c === "" || (($temp = strlen($u) - strlen($c)) >= 0 && strpos($u, $c, $temp) !== false);
                    }
                    break;
                case "startsWith":
                    if (is_string($u) && is_string($c)) {
                        return strpos($u, $c) === 0;
                    }
                    break;
                case "matches":
                    if (is_string($u) && is_string($c)) {
                    error_log("u: $u c: $c");
                        //PHP can do subpatterns, but everything needs to be wrapped in an outer ():
                        return preg_match("($c)", $u) === 1;
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
        } catch (Exception $e) {
            //TODO: log warning
        }
        return false;
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