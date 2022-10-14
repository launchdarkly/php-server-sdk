<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

/**
 * An attribute name or path expression identifying a value within an {@link LDContext}.
 *
 * Unlike some other SDKs, this class is not exposed in the public API. Due to the
 * stateless nature of typical PHP applications, there is little advantage in pre-parsing
 * attribute references and storing them internally. This class just encapsulates the
 * parsing logic.
 *
 * @ignore
 * @internal
 */
class AttributeReference
{
    const ERR_ATTR_EMPTY = 'attribute reference cannot be empty';
    const ERR_ATTR_EXTRA_SLASH = 'attribute reference contained a double slash or a trailing slash';
    const ERR_ATTR_INVALID_ESCAPE =
        'attribute reference contained an escape character (~) that was not followed by 0 or 1';

    public static function parse(string $refPath): string|array
    {
        if ($refPath === '' || $refPath === '/') {
            throw new InvalidAttributeReferenceException(self::ERR_ATTR_EMPTY);
        }
        if (!str_starts_with($refPath, '/')) {
            return $refPath;
        }
        $components = explode('/', substr($refPath, 1));
        for ($i = 0; $i < count($components); $i++) {
            $prop = self::unescape($components[$i]);
            if ($prop === '') {
                throw new InvalidAttributeReferenceException(self::ERR_ATTR_EXTRA_SLASH);
            }
            $components[$i] = $prop;
        }
        return $components;
    }

    private static function unescape(string $s): string
    {
        if (preg_match('/(~[^01]|~$)/', $s)) {
            throw new InvalidAttributeReferenceException(self::ERR_ATTR_INVALID_ESCAPE);
        }
        return str_replace('~0', '~', str_replace('~1', '/', $s));
    }
}
