<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use DateTime;
use DateTimeInterface;
use Exception;
use LaunchDarkly\Impl\SemanticVersion;
use LaunchDarkly\Impl\Util;

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

    public static function apply(?string $op, mixed $u, mixed $c): bool
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
                    if (self::is_numeric($u) && self::is_numeric($c)) {
                        return $u < $c;
                    }
                    break;
                case "lessThanOrEqual":
                    if (self::is_numeric($u) && self::is_numeric($c)) {
                        return $u <= $c;
                    }
                    break;
                case "greaterThan":
                    if (self::is_numeric($u) && self::is_numeric($c)) {
                        return $u > $c;
                    }
                    break;
                case "greaterThanOrEqual":
                    if (self::is_numeric($u) && self::is_numeric($c)) {
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
                    return self::semver_operator($u, $c, 0);
                case "semVerLessThan":
                    return self::semver_operator($u, $c, -1);
                case "semVerGreaterThan":
                    return self::semver_operator($u, $c, 1);
            }
        } catch (Exception $ignored) {
        }
        return false;
    }

    private static function semver_operator(mixed $u, mixed $c, int $expectedComparisonResult): bool
    {
        if (!is_string($u) || !is_string($c)) {
            return false;
        }
        $uVer = self::parseSemVer($u);
        $cVer = self::parseSemVer($c);
        return ($uVer != null) && ($cVer != null) && $uVer->comparePrecedence($cVer) == $expectedComparisonResult;
    }

    /**
     * A stricter version of the built-in is_numeric checker.
     *
     * This version will check if the provided value is numeric, but isn't a
     * string. This helps us stay consistent with the way flag evaluations work
     * in more strictly typed languages.
     *
     * @param mixed $value
     * @return bool
     */
    public static function is_numeric(mixed $value): bool
    {
        return is_numeric($value) && !is_string($value);
    }

    /**
     * @param mixed $in
     * @return ?int
     */
    public static function parseTime(mixed $in): ?int
    {
        if (is_string($in)) {
            $dateTime = DateTime::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $in);
            if ($dateTime == null) {
                // try the same format but without fractional seconds
                $dateTime = DateTime::createFromFormat(DateTimeInterface::RFC3339, $in);
            }
            if ($dateTime == null) {
                return null;
            }
            return Util::dateTimeToUnixMillis($dateTime);
        }

        if (is_numeric($in)) { // check this after is_string, because a numeric string would return true
            return (int)$in;
        }

        if ($in instanceof DateTime) {
            return Util::dateTimeToUnixMillis($in);
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
