<?php

namespace LaunchDarkly;

/**
 * A minimal implementation of the Semantic Versioning 2.0.0 standard (http://semver.org).
 * Supports only string parsing and precedence comparison.  The one departure from the
 * standard is that in "loose" mode, the minor and patch versions can be omitted
 * (defaulting to zero).
 */
class SemanticVersion
{
    private static $REGEX = '/^(?<major>0|[1-9]\d*)(\.(?<minor>0|[1-9]\d*))?(\.(?<patch>0|[1-9]\d*))?(\-(?<prerel>[0-9A-Za-z\-\.]+))?(\+(?<build>[0-9A-Za-z\-\.]+))?$/';

    /** @var int */
    public $major;
    /** @var int */
    public $minor;
    /** @var int */
    public $patch;
    /** @var string */
    public $prerelease;
    /** @var string */
    public $build;

    public function __construct($major, $minor, $patch, $prerelease, $build)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
        $this->prerelease = $prerelease;
        $this->build = $build;
    }

    /**
     * Attempts to parse a string as a semantic version.
     * @param $input string the input string
     * @param $loose boolean true if minor and patch versions can be omitted
     * @return a SemanticVersion object
     * @throws InvalidArgumentException if the string is not in an acceptable format
     */
    public static function parse($input, $loose = false)
    {
        if (!preg_match(self::$REGEX, $input, $matches)) {
            throw new \InvalidArgumentException("not a valid semantic version");
        }
        $major = intval($matches['major']);
        if (!$loose && (!array_key_exists('minor', $matches) || !array_key_exists('patch', $matches))) {
            throw new \InvalidArgumentException("not a valid semantic version: minor and patch versions are required");
        }
        $minor = array_key_exists('minor', $matches) ? intval($matches['minor']) : 0;
        $patch = array_key_exists('patch', $matches) ? intval($matches['patch']) : 0;
        $prerelease = array_key_exists('prerel', $matches) ? $matches['prerel'] : '';
        $build = array_key_exists('build', $matches) ? $matches['build'] : '';
        return new SemanticVersion($major, $minor, $patch, $prerelease, $build);
    }

    /**
     * Compares this version to another version using Semantic Versioning precedence rules.
     * @param $other a SemanticVersion object
     * @return -1 if this version has lower precedence than the other version; 1 if this version
     *   has higher precedence; zero if the two have equal precedence
     */
    public function comparePrecedence($other)
    {
        if ($this->major != $other->major) {
            return ($this->major < $other->major) ? -1 : 1;
        }
        if ($this->minor != $other->minor) {
            return ($this->minor < $other->minor) ? -1 : 1;
        }
        if ($this->patch != $other->patch) {
            return ($this->patch < $other->patch) ? -1 : 1;
        }
        if ($this->prerelease != $other->prerelease) {
            // *no* prerelease component always has a higher precedence than *any* prerelease component
            if ($this->prerelease == '') {
                return 1;
            }
            if ($other->prerelease == '') {
                return -1;
            }
            return self::compareIdentifiers(explode('.', $this->prerelease), explode('.', $other->prerelease));
        }
        // build metadata is always ignored in precedence comparison
        return 0;
    }

    private static function compareIdentifiers($ids1, $ids2)
    {
        for ($i = 0; ; $i++) {
            if ($i >= count($ids1)) {
                // x.y is always less than x.y.z
                return ($i >= count($ids2)) ? 0 : -1;
            }
            if ($i >= count($ids2)) {
                return 1;
            }
            $v1 = $ids1[$i];
            $v2 = $ids2[$i];
            // each sub-identifier is compared numerically if both are numeric; if both are non-numeric,
            // they're compared as strings; otherwise, the numeric one is the lesser one
            $isNum1 = is_numeric($v1);
            $isNum2 = is_numeric($v2);
            if ($isNum1 && $isNum2) {
                $n1 = intval($v1);
                $n2 = intval($v2);
                $d = ($n1 == $n2) ? 0 : (($n1 < $n2) ? -1 : 1);
            } else {
                if ($isNum1 || $isNum2) {
                    $d = $isNum1 ? -1 : 1;
                } else {
                    $d = ($v1 == $v2) ? 0 : (($v1 < $v2) ? -1 : 1);
                }
            }
            if ($d != 0) {
                return $d;
            }
        }
    }
}
