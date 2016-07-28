<?php
namespace LaunchDarkly;

class Operator {
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
                    break;
                case "after":
                    break;
            }
        } finally {
            return false;
        }
    }
}