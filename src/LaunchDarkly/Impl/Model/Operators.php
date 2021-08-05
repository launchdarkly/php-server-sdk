<?php
namespace LaunchDarkly\Impl\Model;

use LaunchDarkly\Impl\SemanticVersion;
use LaunchDarkly\Impl\Util;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * Internal class used in feature flag evaluations.
 *
 * @ignore
 * @internal
 */
class Operators
{
    /** @var string */
    const RFC3339 = 'Y-m-d\TH:i:s.uP';

    /** @var string */
    const VERSION_NUMBERS_REGEX = '/^\\d+(\\.\\d+)?(\\.\\d+)?/';

    /**
     * @param mixed|null $u
     * @param mixed|null $c
     */
    public static function apply(?string $op, $u, $c): bool
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
                case "semVerEqual":
                    $uVer = self::parseSemVer($u);
                    $cVer = self::parseSemVer($c);
                    return ($uVer != null) && ($cVer != null) && $uVer->comparePrecedence($cVer) == 0;
                case "semVerLessThan":
                    $uVer = self::parseSemVer($u);
                    $cVer = self::parseSemVer($c);
                    return ($uVer != null) && ($cVer != null) && $uVer->comparePrecedence($cVer) < 0;
                case "semVerGreaterThan":
                    $uVer = self::parseSemVer($u);
                    $cVer = self::parseSemVer($c);
                    return ($uVer != null) && ($cVer != null) && $uVer->comparePrecedence($cVer) > 0;
            }
        } catch (Exception $ignored) {
        }
        return false;
    }

    /**
     * @param mixed|null $in 
     * @return ?numeric
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
                $dateTime = new DateTime($in, new DateTimeZone('UTC'));
                return Util::dateTimeToUnixMillis($dateTime);
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    public static function parseSemVer(string $in): ?SemanticVersion
    {
        try {
            return SemanticVersion::parse($in, true);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
