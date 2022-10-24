<?php

declare(strict_types=1);

namespace LaunchDarkly\Types;

/**
 * An attribute name or path expression identifying a value within an {@see \LaunchDarkly\LDContext}.
 *
 * Applications are unlikely to need to use this type directly, but see below for details of the
 * attribute reference syntax used by methods like {@see \LaunchDarkly\LDContextBuilder::private()}.
 *
 * The string representation of an attribute reference in LaunchDarkly data uses the following
 * syntax:
 *
 * - If the first character is not a slash, the string is interpreted literally as an
 * attribute name. An attribute name can contain any characters, but must not be empty.
 * - If the first character is a slash, the string is interpreted as a slash-delimited
 * path where the first path component is an attribute name, and each subsequent path
 * component is the name of a property in a JSON object. Any instances of the characters "/"
 * or "~" in a path component are escaped as "~1" or "~0" respectively. This syntax
 * deliberately resembles JSON Pointer, but no JSON Pointer behaviors other than those
 * mentioned here are supported.
 */
class AttributeReference
{
    const ERR_ATTR_EMPTY = 'attribute reference cannot be empty';
    const ERR_ATTR_EXTRA_SLASH = 'attribute reference contained a double slash or a trailing slash';
    const ERR_ATTR_INVALID_ESCAPE =
        'attribute reference contained an escape character (~) that was not followed by 0 or 1';

    private string $_path;
    private ?string $_singleComponent;
    /** @var string[]|null */
    private ?array $_components;
    private ?string $_error;

    private function __construct(string $path, ?string $singleComponent, ?array $components, ?string $error)
    {
        $this->_path = $path;
        $this->_singleComponent = $singleComponent;
        $this->_components = $components;
        $this->_error = $error;
    }

    /**
     * Creates an AttributeReference from a string. For the supported syntax and examples, see
     * comments on the {@see \LaunchDarkly\AttributeReference} type.
     *
     * This method always returns an AttributeRef that preserves the original string, even if
     * validation fails. If validation fails, {@see \LaunchDarkly\AttributeReference::getError()} will
     * return a non-null error and any SDK method that takes this AttributeReference as a parameter
     * will consider it invalid.
     *
     * @param string $refPath an attribute name or path
     * @return AttributeReference the parsed reference
     */
    public static function fromPath(string $refPath): AttributeReference
    {
        if ($refPath === '' || $refPath === '/') {
            return self::failed($refPath, self::ERR_ATTR_EMPTY);
        }
        if (!str_starts_with($refPath, '/')) {
            return new AttributeReference($refPath, $refPath, null, null);
        }
        $components = explode('/', substr($refPath, 1));
        if (count($components) === 1) {
            $attr = self::unescape($components[0]);
            if ($attr === null) {
                return self::failed($refPath, self::ERR_ATTR_INVALID_ESCAPE);
            }
            return new AttributeReference($refPath, $attr, null, null);
        }
        for ($i = 0; $i < count($components); $i++) {
            $prop = $components[$i];
            if ($prop === '') {
                return self::failed($refPath, self::ERR_ATTR_EXTRA_SLASH);
            }
            $prop = self::unescape($prop);
            if ($prop === null) {
                return self::failed($refPath, self::ERR_ATTR_INVALID_ESCAPE);
            }
            $components[$i] = $prop;
        }
        return new AttributeReference($refPath, null, $components, null);
    }

    /**
     * Similar to {@see \LaunchDarkly\AttributeReference::fromPath()}, except that it always
     * interprets the string as a literal attribute name, never as a slash-delimited path expression.
     *
     * There is no escaping or unescaping, even if the name contains literal '/' or '~' characters.
     * Since an attribute name can contain any characters, this method always returns a valid
     * AttributeReference unless the name is empty.
     *
     * @param string $attributeName an attribute name
     * @param AttributeReference the reference
     */
    public static function fromLiteral(string $attributeName): AttributeReference
    {
        if ($attributeName === '') {
            return self::failed($attributeName, self::ERR_ATTR_EMPTY);
        }
        // If the attribute name starts with a slash, we need to compute the escaped version so
        // getPath() will always return a valid attribute reference path. This matters because
        // lists of redacted attributes in events always use the path format.
        $refPath = str_starts_with($attributeName, '/') ?
            ('/' . self::escape($attributeName)) :
            $attributeName;
        return new AttributeReference($refPath, $attributeName, null, null);
    }

    private static function failed(string $refPath, string $error): AttributeReference
    {
        return new AttributeReference($refPath, null, null, $error);
    }

    /**
     * Returns the original attribute reference path string.
     */
    public function getPath(): string
    {
        return $this->_path;
    }

    /**
     * Returns null for a valid reference, or an error string for an invalid one.
     *
     * @return ?string an error string or null
     */
    public function getError(): ?string
    {
        return $this->_error;
    }

    /**
     * The number of path components in the AttributeReference.
     *
     * For a simple attribute reference such as "name" with no leading slash, this returns 1.
     *
     * For an attribute reference with a leading slash, it is the number of slash-delimited path
     * components after the initial slash. For instance, for "/a/b" it returns 2.
     *
     * For an invalid attribute reference, it returns zero.
     *
     * @return int the number of path components
     */
    public function getDepth(): int
    {
        return $this->_components === null ? 1 : count($this->_components);
    }

    /**
     * Retrieves a single path component from the attribute reference.
     *
     * For a simple attribute reference such as "name" with no leading slash, it returns the
     * attribute name if index is zero, and an empty string otherwise.
     *
     * For an attribute reference with a leading slash, if index is non-negative and less than
     * getDepth(), it returns the path component string at that position.
     *
     * @param int index the zero-based index of the desired path component
     * @return string the path component, or an empty string if not available
     */
    public function getComponent(int $index): string
    {
        if ($this->_components === null) {
            return $index === 0 ? ($this->_singleComponent ?: '') : '';
        }
        return $index < 0 || $index >= count($this->_components) ? '' : $this->_components[$index];
    }

    private static function unescape(string $s): ?string
    {
        if (preg_match('/(~[^01]|~$)/', $s)) {
            return null;
        }
        return str_replace('~0', '~', str_replace('~1', '/', $s));
    }

    private static function escape(string $s): ?string
    {
        return str_replace('/', '~1', str_replace('~', '~0', $s));
    }
}
