<?php
namespace LaunchDarkly;

use DateTime;
use Exception;

class Operators
{
    const RFC3339 = 'Y-m-d\TH:i:s.uP';

    /**
     * @param $op string
     * @param $u
     * @param $c
     * @return bool
     */
    public static function apply($op, $u, $c)
    {
        try {
            if ($u === null || $c === null) {
                return false;
            }
            switch ($op) {
                case "in":
                    if ($u === $c) {
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
                        //PHP can do subpatterns, but everything needs to be wrapped in an outer ():
                        return preg_match("($c)", $u) === 1;
                    }
                    break;
                case "contains":
                    if (is_string($u) && is_string($c)) {
                        return strpos($u, $c) !== false;
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
        } catch (Exception $ignored) {
        }
        return false;
    }

    /**
     * @param $in
     * @return null|int|float
     */
    public static function parseTime($in)
    {
        if (is_numeric($in)) {
            return $in;
        }

        if ($in instanceof DateTime) {
            return Util::dateTimeToUnixMillis($in);
        }

        if (is_string($in)) {
            try {
                $dateTime = new DateTime($in);
                return Util::dateTimeToUnixMillis($dateTime);
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }
}
