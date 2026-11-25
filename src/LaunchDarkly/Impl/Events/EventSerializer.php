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
    // This is the default depth limit of json_encode. Redaction stops at the same depth. A value
    // that is nested more deeply, or that contains a cycle, makes json_encode fail.
    private const MAX_JSON_DEPTH = 512;


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
        $ret = json_encode($filtered, 0, self::MAX_JSON_DEPTH);
        if ($ret === false) {
            return '';
        }
        return $ret;
    }

    private function filterEvent(array $e): array
    {
        // Feature, custom, and migration op events inline the full context, so anonymous context
        // attributes must be redacted for all of them. Other event kinds do not redact anonymous
        // attributes.
        $redactAnonymousAttributes = in_array($e['kind'] ?? '', ['feature', 'custom', 'migration_op'], true);

        $ret = [];
        foreach ($e as $key => $value) {
            if ($key == 'context') {
                $ret[$key] = $this->serializeContext($value, $redactAnonymousAttributes);
            } else {
                $ret[$key] = $value;
            }
        }
        return $ret;
    }

    private function serializeContext(LDContext $context, bool $redactAnonymousAttributes): array
    {
        if ($context->isMultiple()) {
            $ret = ['kind' => 'multi'];
            for ($i = 0; $i < $context->getIndividualContextCount(); $i++) {
                $c = $context->getIndividualContext($i);
                if ($c !== null) {
                    $ret[$c->getKind()] = $this->serializeContextSingleKind($c, false, $redactAnonymousAttributes);
                }
            }
            return $ret;
        } else {
            return $this->serializeContextSingleKind($context, true, $redactAnonymousAttributes);
        }
    }

    private function serializeContextSingleKind(LDContext $c, bool $includeKind, bool $redactAnonymousAttributes): array
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
        $redactAllAttributes = $this->_allAttributesPrivate || ($redactAnonymousAttributes && $c->isAnonymous());
        if ($c->getName() !== null && !$this->checkWholeAttributePrivate('name', $allPrivate, $redacted, $redactAllAttributes)) {
            $ret['name'] = $c->getName();
        }
        foreach ($c->getCustomAttributeNames() as $attr) {
            if (!$this->checkWholeAttributePrivate($attr, $allPrivate, $redacted, $redactAllAttributes)) {
                $value = $c->get($attr);
                $ret[$attr] = self::redactJsonValue(null, $attr, $value, $allPrivate, $redacted);
            }
        }
        if (count($redacted) !== 0) {
            $ret['_meta'] = ['redactedAttributes' => $redacted];
        }
        return $ret;
    }

    private function checkWholeAttributePrivate(string $attr, array $allPrivate, array &$redactedOut, bool $redactAllAttributes): bool
    {
        if ($redactAllAttributes) {
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
        // A JSON object can be a stdClass (for example, from json_decode) or an array. json_encode
        // writes an array as a JSON list only if array_is_list is true for it. It writes any other
        // array as a JSON object, and numeric keys become property names. We use the same rule.
        // Attribute references do not index into lists, so we do not change a list.
        if ($value instanceof \stdClass) {
            $props = get_object_vars($value);
        } elseif (is_array($value) && !array_is_list($value)) {
            $props = $value;
        } else {
            return $value;
        }
        if (count($props) === 0) {
            return $value;
        }
        $currentPath = $parentPath ?? [];
        $currentPath[] = $name;
        if (count($currentPath) >= self::MAX_JSON_DEPTH) {
            // The event JSON wraps this value in more levels, so json_encode fails and sends
            // nothing. This also stops the recursion on a value that contains a cycle.
            return $value;
        }
        $ret = [];
        foreach ($props as $k => $v) {
            // PHP gives numeric keys as ints. Path components are strings.
            $k = (string)$k;
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
        // Return an object, so json_encode writes a JSON object. If we return an array, the result
        // can be a list after redaction (for example, only key 0 is left), or empty, and
        // json_encode then writes it as a JSON list.
        return (object)$ret;
    }
}
