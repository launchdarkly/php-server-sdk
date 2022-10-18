<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

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

    public static function parse(string $refPath): AttributeReference
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

    private static function failed(string $refPath, string $error): AttributeReference
    {
        return new AttributeReference($refPath, null, null, $error);
    }

    public function getPath(): string
    {
        return $this->_path;
    }

    public function getError(): ?string
    {
        return $this->_error;
    }

    public function getDepth(): int
    {
        return $this->_components === null ? 1 : count($this->_components);
    }

    public function getComponent(int $index): string
    {
        if ($this->_components === null) {
            return $index === 0 ? $this->_singleComponent : '';
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
}
