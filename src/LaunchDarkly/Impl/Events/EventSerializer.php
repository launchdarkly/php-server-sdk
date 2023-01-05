<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Events;

use LaunchDarkly\LDContext;
use LaunchDarkly\Types\AttributeReference;

/**
 * Internal class that translates analytics events into the format used for sending them to LaunchDarkly.
 *
 * @ignore
 * @internal
 */
class EventSerializer
{
    private bool $_allAttributesPrivate;
    /** @var AttributeReference[] */
    private array $_privateAttributes;

    public function __construct(array $options)
    {
        $this->_allAttributesPrivate = !!($options['all_attributes_private'] ?? false);

        $allParsedPrivate = [];
        foreach ($options['private_attribute_names'] ?? [] as $attr) {
            $parsed = AttributeReference::fromPath($attr);
            if ($parsed->getError() === null) {
                $allParsedPrivate[] = $parsed;
            }
        }
        $this->_privateAttributes = $allParsedPrivate;
    }

    public function serializeEvents(array $events): string
    {
        $filtered = [];
        foreach ($events as $e) {
            $filtered[] = $this->filterEvent($e);
        }
        $ret = json_encode($filtered);
        if ($ret === false) {
            return '';
        }
        return $ret;
    }

    private function filterEvent(array $e): array
    {
        $ret = [];
        foreach ($e as $key => $value) {
            if ($key == 'context') {
                $ret[$key] = $this->serializeContext($value);
            } else {
                $ret[$key] = $value;
            }
        }
        return $ret;
    }

    private function serializeContext(LDContext $context): array
    {
        if ($context->isMultiple()) {
            $ret = ['kind' => 'multi'];
            for ($i = 0; $i < $context->getIndividualContextCount(); $i++) {
                $c = $context->getIndividualContext($i);
                if ($c !== null) {
                    $ret[$c->getKind()] = $this->serializeContextSingleKind($c, false);
                }
            }
            return $ret;
        } else {
            return $this->serializeContextSingleKind($context, true);
        }
    }

    private function serializeContextSingleKind(LDContext $c, bool $includeKind): array
    {
        $ret = ['key' => $c->getKey()];
        if ($includeKind) {
            $ret['kind'] = $c->getKind();
        }
        if ($c->isAnonymous()) {
            $ret['anonymous'] = true;
        }
        $redacted = [];
        $allPrivate = array_merge($this->_privateAttributes, $c->getPrivateAttributes() ?? []);
        if ($c->getName() !== null && !$this->checkWholeAttributePrivate('name', $allPrivate, $redacted)) {
            $ret['name'] = $c->getName();
        }
        foreach ($c->getCustomAttributeNames() as $attr) {
            if (!$this->checkWholeAttributePrivate($attr, $allPrivate, $redacted)) {
                $value = $c->get($attr);
                $ret[$attr] = self::redactJsonValue(null, $attr, $value, $allPrivate, $redacted);
            }
        }
        if (count($redacted) !== 0) {
            $ret['_meta'] = ['redactedAttributes' => $redacted];
        }
        return $ret;
    }

    private function checkWholeAttributePrivate(string $attr, array $allPrivate, array &$redactedOut): bool
    {
        if ($this->_allAttributesPrivate) {
            $redactedOut[] = $attr;
            return true;
        }
        foreach ($allPrivate as $p) {
            if ($p->getComponent(0) === $attr && $p->getDepth() === 1) {
                $redactedOut[] = $attr;
                return true;
            }
        }
        return false;
    }

    private static function redactJsonValue(?array $parentPath, string $name, mixed $value, array $allPrivate, array &$redactedOut): mixed
    {
        if (!is_array($value) || count($value) === 0) {
            return $value;
        }
        $ret = [];
        $currentPath = $parentPath ?? [];
        $currentPath[] = $name;
        foreach ($value as $k => $v) {
            if (is_int($k)) {
                // This is a regular array, not an object with string properties-- redactions don't apply. Technically,
                // that's not a 100% solid assumption because in PHP, an array could have a mix of int and string keys.
                // But that's not true in JSON or in pretty much any other SDK, so there wouldn't really be any clear
                // way to apply our redaction logic in that case anyway.
                return $value;
            }
            $wasRedacted = false;
            foreach ($allPrivate as $p) {
                if ($p->getDepth() !== count($currentPath) + 1) {
                    continue;
                }
                if ($p->getComponent(count($currentPath)) !== $k) {
                    continue;
                }
                $match = true;
                for ($i = 0; $i < count($currentPath); $i++) {
                    if ($p->getComponent($i) !== $currentPath[$i]) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    $redactedOut[] = $p->getPath();
                    $wasRedacted = true;
                    break;
                }
            }
            if (!$wasRedacted) {
                $ret[$k] = self::redactJsonValue($currentPath, $k, $v, $allPrivate, $redactedOut);
            }
        }
        if (count($ret) === 0) {
            // Substitute an empty object here, because an empty array would serialize as [] rather than {}
            return new \stdClass();
        }
        return $ret;
    }
}
