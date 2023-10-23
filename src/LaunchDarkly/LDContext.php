<?php

declare(strict_types=1);

namespace LaunchDarkly;

use LaunchDarkly\Types\AttributeReference;

/**
 * A collection of attributes that can be referenced in flag evaluations and analytics events.
 * This entity is also called an "evaluation context."
 *
 * To create an LDContext of a single kind, such as a user, you may use
 * {@see \LaunchDarkly\LDContext::create()} when only the key and the kind are relevant; or, to
 * specify other attributes, use {@see \LaunchDarkly\LDContext::builder()}.
 *
 * To create an LDContext with multiple kinds (a multi-context), use
 * {@see \LaunchDarkly\LDContext::createMulti()} or {@see \LaunchDarkly\LDContext::multiBuilder()}.
 *
 * An LDContext can be in an error state if it was built with invalid attributes. See
 * {@see \LaunchDarkly\LDContext::isValid()} and {@see \LaunchDarkly\LDContext::getError()}.
 */
class LDContext implements \JsonSerializable
{
    /**
     * A constant for the default kind of "user".
     */
    public const DEFAULT_KIND = 'user';

    /**
     * A constant for the kind that all multi-contexts have.
     */
    public const MULTI_KIND = 'multi';

    private const ERR_NO_KEY = 'context key must not be null or empty';
    private const ERR_KIND_CANNOT_BE_EMPTY = 'context kind must not be empty';
    private const ERR_KIND_CANNOT_BE_KIND = '"kind" is not a valid context kind';
    private const ERR_KIND_INVALID_CHARS = 'context kind contains disallowed characters';
    private const ERR_KIND_MULTI_FOR_SINGLE = 'context of kind "multi" must be created with createMulti or multiBuilder';
    private const ERR_KIND_MULTI_WITH_NO_KINDS = 'multi-context must contain at least one kind';
    private const ERR_KIND_MULTI_DUPLICATES = 'multi-kind context cannot have same kind more than once';
    private const ERR_KIND_NON_STRING = 'context kind must be a string';

    private string $_kind;
    private string $_key;
    private ?string $_name = null;
    private bool $_anonymous = false;
    private ?array $_attributes = null;
    /** @var AttributeReference[]|null */
    private ?array $_privateAttributes = null;
    /** @var LDContext[]|null */
    private ?array $_multiContexts = null;
    private ?string $_error = null;

    /**
     * Constructs an instance, setting all properties. Avoid using this constructor directly.
     *
     * Applications should not normally use this constructor; the intended pattern is to use
     * factory methods or builders. Calling this constructor directly may result in some context
     * validation being skipped.
     *
     * @param ?string $kind the context kind
     * @param string $key the context key
     * @param ?string $name the optional name attribute
     * @param bool $anonymous the anonymous attribute
     * @param mixed[]|null $attributes associative array of additional attributes
     * @param AttributeReference[]|null $privateAttributes private attribute references
     * @param LDContext[]|null $multiContexts contexts within this if this is a multi-context
     * @param ?string $error error string or null if valid
     */
    public function __construct(
        ?string $kind,
        string $key,
        ?string $name,
        bool $anonymous,
        ?array $attributes,
        ?array $privateAttributes,
        ?array $multiContexts,
        ?string $error
    ) {
        if ($error) {
            $this->makeInvalid($error);
            return;
        }
        if ($multiContexts !== null) {
            if (count($multiContexts) === 0) {
                $this->makeInvalid(self::ERR_KIND_MULTI_WITH_NO_KINDS);
                return;
            }
            $errors = null;
            for ($i = 0; $i < count($multiContexts); $i++) {
                $c = $multiContexts[$i];
                if (!($c instanceof LDContext)) {
                    $this->makeInvalid('something other than an LDContext was used in a multi-context');
                    return;
                }
                if (!$c->isValid()) {
                    if ($errors === null) {
                        $errors = '';
                    } else {
                        $errors .= ', ';
                    }
                    $errors .= $c->getError();
                    continue;
                }
                for ($j = 0; $j < $i; $j++) {
                    if ($multiContexts[$j]->getKind() === $c->getKind()) {
                        $this->makeInvalid(self::ERR_KIND_MULTI_DUPLICATES);
                        return;
                    }
                }
            }
            if ($errors) {
                $this->makeInvalid($errors);
                return;
            }
            // Sort them by kind; they need to be sorted for computing a fully-qualified key, but even
            // if getFullyQualifiedKey() is never called, this is helpful for equals() and determinacy.
            $sorted = $multiContexts;
            usort($sorted, fn (LDContext $c1, LDContext $c2) => $c1->_kind <=> $c2->_kind);
            $this->_multiContexts = $sorted;
            $this->_kind = self::MULTI_KIND;
            // No other properties can be set for a multi-context, but we'll still ensure that all
            // properties that are normally non-null have non-null values.
            $this->_key = '';
            return;
        }
        if ($kind === null || $kind === '') {
            $kind = self::DEFAULT_KIND;
        }
        $kindError = self::validateKind($kind);
        if ($kindError) {
            self::makeInvalid($kindError);
            return;
        }
        if ($key === '') {
            self::makeInvalid(self::ERR_NO_KEY);
            return;
        }
        $this->_key = $key;
        $this->_kind = $kind;
        $this->_name = $name;
        $this->_anonymous = $anonymous;
        $this->_attributes = $attributes;
        $this->_privateAttributes = $privateAttributes;
    }

    /**
     * Creates a single-kind LDContext with only the key and the kind specified.
     *
     * If you omit the kind, it defaults to "user" ({@see \LaunchDarkly\LDContext::DEFAULT_KIND}).
     *
     * To specify additional properties, use {@see \LaunchDarkly\LDContext::builder()}. To create a
     * multi-context instead of a single one, use {@see \LaunchDarkly\LDContext::createMulti()} or
     * {@see \LaunchDarkly\LDContext::multiBuilder()}.
     *
     * @param string $key the context key
     * @param string|null $kind the context kind; if null, {@see \LaunchDarkly\LDContext::DEFAULT_KIND} is used
     * @return LDContext an LDContext
     * @see \LaunchDarkly\LDContext::createMulti()
     * @see \LaunchDarkly\LDContext::builder()
     */
    public static function create(string $key, ?string $kind = null): LDContext
    {
        return new LDContext($kind, $key, null, false, null, null, null, null);
    }

    /**
     * Creates a multi-context out of the specified single-kind LDContexts.
     *
     * To create an LDContext for a single context kind, use {@see \LaunchDarkly\LDContext::create()}
     * or {@see \LaunchDarkly\LDContext::builder()}.
     *
     * For the returned LDContext to be valid, the contexts list must not be empty, and all of its
     * elements must be valid LDContexts. Otherwise, the returned LDContext will be invalid as
     * reported by {@see \LaunchDarkly\LDContext::getError()}.
     *
     * If only one context parameter is given, the method returns that same context.
     *
     * If the nested context is a multi-context, this is exactly equivalent to adding each of the
     * individual kinds from it separately. See {@see \LaunchDarkly\LDContextMultiBuilder::add()}.
     *
     * @param LDContext $contexts,... a list of contexts
     * @return LDContext an LDContext
     * @see \LaunchDarkly\LDContext::create()
     * @see \LaunchDarkly\LDContext::multiBuilder()
     */
    public static function createMulti(LDContext ...$contexts): LDContext
    {
        if (count($contexts) === 0) {
            return self::createWithError(self::ERR_KIND_MULTI_WITH_NO_KINDS);
        }
        $b = self::multiBuilder();
        foreach ($contexts as $c) {
            $b->add($c);
        }
        return $b->build();
    }

    /**
     * Creates a builder for building an LDContext.
     *
     * You may use {@see \LaunchDarkly\LDContextBuilder} methods to set additional attributes and/or
     * change the `kind` before calling {@see \LaunchDarkly\LDContextBuilder::build()}. If you do not
     * change any values, the defaults for the LDContext are that its `kind` is
     * {@see \LaunchDarkly\LDContext::DEFAULT_KIND} ("user"), its `key` is set to the key parameter
     * specified here, `anonymous` is `false`, and it has no values for any other attributes.
     *
     * This method is for building an LDContext that has only a single kind. To define a multi-context,
     * use {@see \LaunchDarkly\LDContext::createMulti()} or {@see \LaunchDarkly\LDContext::multiBuilder()}.
     *
     * If `key` is an empty string, there is no default. An LDContext must have a non-empty key, so if
     * you call {@see \LaunchDarkly\LDContextBuilder::build()} in this state without using
     * {@see \LaunchDarkly\LDContextBuilder::key()} to set the key, you will get an invalid LDContext.
     *
     * @param string $key the context key
     * @return LDContextBuilder a builder
     * @see \LaunchDarkly\LDContext::multiBuilder()
     * @see \LaunchDarkly\LDContext::create()
     */
    public static function builder(string $key): LDContextBuilder
    {
        return new LDContextBuilder($key);
    }

    /**
     * Creates a builder for building a multi-context.
     *
     * This method is for building an LDContext that contains multiple contexts, each for a different
     * context kind. To define a single context, use {@see \LaunchDarkly\LDContext::builder()}
     * or {@see \LaunchDarkly\LDContext::create()} instead.
     *
     * The difference between this method and {@see \LaunchDarkly\LDContext::createMulti()} is
     * simply that the builder allows you to add contexts one at a time, if that is more
     * convenient for your logic.
     *
     * @return LDContextMultiBuilder a builder
     * @see \LaunchDarkly\LDContext::createMulti()
     * @see \LaunchDarkly\LDContext::builder()
     */
    public static function multiBuilder(): LDContextMultiBuilder
    {
        return new LDContextMultiBuilder();
    }

    /**
     * Creates an LDContext from a parsed JSON representation.
     *
     * The JSON must be in one of the standard formats used by LaunchDarkly.
     *
     * ```php
     *     $json = '{"kind": "user", "key": "aaa"}';
     *     $context = LDContext::fromJson($json);
     *
     *     // or:
     *     $props = ['kind' => 'user', 'key' => 'true'];
     *     $context = LDContext::fromJson($props);
     * ```
     * @param string|array|object $jsonObject a JSON representation as a string; or, an object or
     *   associative array corresponding to the parsed JSON
     * @return LDContext a context
     * @throws \InvalidArgumentException if any properties were invalid, or if you passed a string that
     *   was not well-formed JSON
     */
    public static function fromJson($jsonObject): LDContext
    {
        if (is_string($jsonObject)) {
            try {
                $o = json_decode($jsonObject, false, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('invalid JSON', 0, $e);
            }
        } else {
            $o = $jsonObject;
        }
        if (!is_array($o) && !is_object($o)) {
            throw new \InvalidArgumentException('invalid JSON object');
        }

        $a = (array)$o;
        $kind = $a['kind'] ?? null;
        if ($kind === self::MULTI_KIND) {
            $b = self::multiBuilder();
            foreach ($a as $k => $v) {
                if ($k != 'kind') {
                    $b->add(self::decodeJsonSingleKind((array)$v, $k));
                }
            }
            return $b->build();
        }
        return self::decodeJsonSingleKind($a, null);
    }

    /**
     * Returns `true` for a valid LDContext, `false` for an invalid one.
     *
     * A valid context is one that can be used in SDK operations. An invalid context is one that
     * is missing necessary attributes or has invalid attributes, indicating an incorrect usage
     * of the SDK API. The only ways for a context to be invalid are:
     *
     * - The `kind` property had a disallowed value. See {@see \LaunchDarkly\LDContextBuilder::kind()}.
     * - For a single context, the `key` property was null or empty.
     * - You tried to create a multi-context without specifying any contexts.
     * - You tried to create a multi-context using the same context kind more than once.
     * - You tried to create a multi-context where at least one of the individual LDContexts was invalid.
     *
     * In any of these cases, isValid() will return false, and {@see \LaunchDarkly\LDContext::getError()}
     * will return a description of the error.
     *
     * Since in normal usage it is easy for applications to be sure they are using context kinds
     * correctly, and because throwing an exception is undesirable in application code that uses
     * LaunchDarkly, the SDK stores the error state in the LDContext itself and checks for such
     * errors at the time the Context is used, such as in a flag evaluation. At that point, if
     * the context is invalid, the operation will fail in some well-defined way as described in
     * the documentation for that method, and the SDK will generally log a warning as well. But
     * in any situation where you are not sure if you have a valid LDContext, you can check
     * isValid() or {@see \LaunchDarkly\LDContext::getError()}.
     *
     * @return bool true if the context is valid
     * @see \LaunchDarkly\LDContext::getError()
     */
    public function isValid(): bool
    {
        return !$this->_error;
    }

    /**
     * Returns `null` for a valid LDContext, or an error message for an invalid one.
     *
     * If this is null, then {@see \LaunchDarkly\LDContext::isValid()} is true. If it is non-null,
     * then {@see \LaunchDarkly\LDContext::isValid()} is false.
     *
     * @return string|null an error description or null
     * @see \LaunchDarkly\LDContext::isValid()
     */
    public function getError(): ?string
    {
        return $this->_error;
    }

    /**
     * Returns true if this is a multi-context.
     *
     * If this value is true, then {@see \LaunchDarkly\LDContext::getKind()} is guaranteed to be
     * {@see \LaunchDarkly\LDContext::MULTI_KIND}, and you can inspect the individual context for
     * each kind with {@see \LaunchDarkly\LDContext::getIndividualContext()}.
     *
     * If this value is false, then {@see \LaunchDarkly\LDContext::getKind()} is guaranteed to
     * return a value that is not {@see \LaunchDarkly\LDContext::MULTI_KIND}.
     *
     * @return bool true for a multi-kind context, false for a single-kind context
     */
    public function isMultiple(): bool
    {
        return $this->_multiContexts !== null;
    }

    /**
     * Returns the context's `kind` attribute.
     *
     * Every valid context has a non-empty kind. For multi-contexts, this value is
     * {@see \LaunchDarkly\LDContext::MULTI_KIND} and the kinds within the context can be
     * inspected with {@see \LaunchDarkly\LDContext::getIndividualContext()}.
     *
     * @return string the context kind
     * @see \LaunchDarkly\LDContextBuilder::kind()
     */
    public function getKind(): string
    {
        return $this->_kind;
    }

    /**
     * Returns an associate array mapping each context kind to its key.
     *
     * If the context is invalid, this will return an empty array. A single
     * kind context will return an array with a single mapping.
     */
    public function getKeys(): array
    {
        if (!$this->isValid()) {
            return [];
        }

        if ($this->_multiContexts !== null) {
            $result = [];
            foreach ($this->_multiContexts as $context) {
                $result[$context->getKind()] = $context->getKey();
            }

            return $result;
        }

        return [$this->getKind() => $this->getKey()];
    }

    /**
     * Returns the context's `key` attribute.
     *
     * For a single context, this value is set by {@see \LaunchDarkly\LDContext::create()},
     * {@see \LaunchDarkly\LDContext::builder()}, or {@see \LaunchDarkly\LDContextBuilder::key()}.
     *
     * For a multi-context, there is no single value and getKey() returns an empty string.
     * empty string. Use {@see \LaunchDarkly\LDContext::getIndividualContext()} to get the
     * context for a particular kind, then call getKey() on it.
     *
     * This value is never null.
     *
     * @return string the context key
     * @see \LaunchDarkly\LDContextBuilder::key()
     */
    public function getKey(): string
    {
        return $this->_key;
    }

    /**
     * Returns the context's `name` attribute.
     *
     * For a single context, this value is set by {@see \LaunchDarkly\LDContextBuilder::name()}.
     * It is null if no value was set.
     *
     * For a multi-context, there is no single value and getName() returns null. Use
     * {@see \LaunchDarkly\LDContext::getIndividualContext()} to get the context for a particular
     * kind, then call getName() on it.
     *
     * @return string|null the context name or null
     * @see \LaunchDarkly\LDContextBuilder::name()
     */
    public function getName(): ?string
    {
        return $this->_name;
    }

    /**
     * Returns true if this context is only intended for flag evaluations and will not be
     * indexed by LaunchDarkly.
     *
     * The default value is false. False means that this LDContext represents an entity
     * such as a user that you want to be able to see on the LaunchDarkly dashboard.
     *
     * Setting `anonymous` to true excludes this context from the database that is
     * used by the dashboard. It does not exclude it from analytics event data, so it is
     * not the same as making attributes private; all non-private attributes will still be
     * included in events and data export. There is no limitation on what other attributes
     * may be included (so, for instance, `anonymous` does not mean there is no `name`),
     * and the context will still have whatever `key` you have given it.
     *
     * This value is also addressable in evaluations as the attribute name "anonymous". It
     * is always treated as a boolean true or false in evaluations.
     *
     * @return bool true if the context should be excluded from the LaunchDarkly database
     * @see \LaunchDarkly\LDContextBuilder::anonymous()
     */
    public function isAnonymous(): bool
    {
        return $this->_anonymous;
    }

    /**
     * Looks up the value of any attribute of the context by name.
     *
     * For a single-kind context, the attribute name can be any custom attribute that was set
     * by {@see \LaunchDarkly\LDContextBuilder::set()}. It can also be one of the built-in ones like
     * "kind", "key", or "name"; in such cases, it is equivalent to
     * {@see \LaunchDarkly\LDContext::getKind()}, {@see \LaunchDarkly\LDContext::getKey()}, or
     * {@see \LaunchDarkly\LDContext::getName()}.
     *
     * For a multi-context, the only supported attribute name is "kind". Use
     * {@see \LaunchDarkly\LDContext::getIndividualContext()} to get the context for a
     * particular kind and then get its attributes.
     *
     * If the value is found, the return value is the attribute value. If there is no such
     * attribute, the return value is `null`. An attribute that actually exists cannot have a
     * null value.
     *
     * @param string $attributeName the desired attribute name
     * @return mixed the attribute value, or null if there is no such attribute
     * @see \LaunchDarkly\LDContextBuilder::set()
     */
    public function get(string $attributeName): mixed
    {
        switch ($attributeName) {
            case 'key':
                return $this->_key;
            case 'kind':
                return $this->_kind;
            case 'name':
                return $this->_name;
            case 'anonymous':
                return $this->_anonymous;
            default:
                if ($this->_attributes === null) {
                    return null;
                }
                return $this->_attributes[$attributeName] ?? null;
        }
    }

    /**
     * Returns the names of all non-built-in attributes that have been set in this context.
     *
     * For a single-kind context, this includes all the names that were passed to
     * {@see \LaunchDarkly\LDContextBuilder::set()} as long as the values were not null (since
     * a null value in LaunchDarkly is equivalent to the attribute not being set).
     *
     * For a multi-context, there are no such names.
     *
     * @return array an array of strings (may be empty, but will never be null)
     */
    public function getCustomAttributeNames(): array
    {
        return $this->_attributes ? array_keys($this->_attributes) : [];
        // Note that array_keys uses the defined traversal order of associative arrays in PHP,
        // which is that you get them in the same order they were added in.
    }

    /**
     * Returns the number of context kinds in this context.
     *
     * For a valid individual context, this returns 1. For a multi-context, it returns the number
     * of context kinds. For an invalid context, it returns zero.
     *
     * @return int the number of context kinds
     */
    public function getIndividualContextCount(): int
    {
        if ($this->_error) {
            return 0;
        }
        return $this->_multiContexts ? count($this->_multiContexts) : 1;
    }

    /**
     * Returns the single-kind LDContext corresponding to one of the kinds in this context.
     *
     * The `kind` parameter can be either a number representing a zero-based index, or a string
     * representing a context kind.
     *
     * If this method is called on a single-kind LDContext, then the only allowable value
     * for `kind` is either zero or the same value as {@see \LaunchDarkly\LDContext::getKind()}
     * , and the return value on success is the same LDContext.
     *
     * If the method is called on a multi-context, and `kind` is a number, it must be a
     * non-negative index that is less than the number of kinds (that is, less than the return
     * value of {@see \LaunchDarkly\LDContext::getIndividualContextCount()}, and the return
     * value on success is one of the individual LDContexts within. Or, if `kind` is a string,
     * it must match the context kind of one of the individual contexts.
     *
     * If there is no context corresponding to `kind`, the method returns null.
     *
     * @param int|string $kind the index or string value of a context kind
     * @return LDContext|null the context corresponding to that index or kind, or null if none
     */
    public function getIndividualContext($kind): ?LDContext
    {
        if (is_string($kind)) {
            if ($this->_multiContexts === null) {
                return $this->getKind() === $kind ? $this : null;
            }
            foreach ($this->_multiContexts as $c) {
                if ($c->getKind() === $kind) {
                    return $c;
                }
            }
            return null;
        }

        if ($this->_multiContexts === null) {
            return $kind === 0 ? $this : null;
        }
        return ($kind >= 0 && $kind < count($this->_multiContexts)) ? $this->_multiContexts[$kind] : null;
    }

    /**
     * Gets the list of all attribute references marked as private for this specific LDContext.
     *
     * This includes all attribute names/paths that were specified with
     * {@see \LaunchDarkly\LDContextBuilder::private()}. If there are none, it is null.
     *
     * @return AttributeReference[]|null the list of private attributes, if any
     */
    public function getPrivateAttributes(): ?array
    {
        return $this->_privateAttributes;
    }

    /**
     * Returns a string that describes the LDContext uniquely based on `kind` and `key` values.
     *
     * This value is used whenever LaunchDarkly needs a string identifier based on all of the
     * `kind` and `key` values in the context. Applications typically do not need to use it.
     *
     * @return string the fully-qualified key
     */
    public function getFullyQualifiedKey(): string
    {
        if (!$this->isValid()) {
            return '';
        }
        if ($this->_multiContexts === null) {
            return $this->_kind === self::DEFAULT_KIND ? $this->_key :
                ($this->_kind . ':' . self::escapeKeyForFullyQualifiedKey($this->_key));
        }
        $ret = '';
        foreach ($this->_multiContexts as $c) {
            if ($ret != '') {
                $ret .= ':';
            }
            $ret .= $c->_kind;
            $ret .= ':';
            $ret .= self::escapeKeyForFullyQualifiedKey($c->_key);
        }
        return $ret;
    }

    /**
     * Tests whether two contexts are logically equal.
     *
     * Equality for single contexts means that all of their attributes are equal. Equality for
     * multi-contexts means that the same context kinds are present in both, and the individual
     * contexts for each kind are equal.
     *
     * @param LDContext $other another context
     * @return bool true if it is equal to this context
     */
    public function equals(LDContext $other): bool
    {
        if ($this->_kind != $other->_kind || $this->_key != $other->_key || $this->_name != $other->_name
            || $this->_anonymous != $other->_anonymous || $this->_attributes != $other->_attributes
            || $this->_privateAttributes != $other->_privateAttributes
            || $this->_error != $other->_error) {
            return false;
            // Note that it's OK to compare _attributes because PHP does a deep-equality check for arrays,
            // and it's OK to compare _privateAttributes because we have canonicalized them by sorting.
        }
        if ($this->_multiContexts === null) {
            return true;
        }
        if ($other->_multiContexts === null || count($this->_multiContexts) != count($other->_multiContexts)) {
            return false;
        }
        for ($i = 0; $i < count($this->_multiContexts); $i++) {
            if (!$this->_multiContexts[$i]->equals($other->_multiContexts[$i])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns a JSON representation of the context (as an associative array), in the format used by
     * LaunchDarkly SDKs.
     *
     * Use this method if you are passing context data to the front end for use with the LaunchDarkly
     * JavaScript SDK.
     *
     * Note that calling json_encode() on an LDContext object will automatically use the jsonSerialize()
     * method.
     *
     * @return array an associative array suitable for passing as a JSON object
     */
    public function jsonSerialize(): array
    {
        if ($this->_multiContexts === null) {
            return $this->jsonSerializeSingleKind(true);
        }
        $ret = ['kind' => self::MULTI_KIND];
        foreach ($this->_multiContexts as $mc) {
            $ret[$mc->_kind] = $mc->jsonSerializeSingleKind(false);
        }
        return $ret;
    }

    /**
     * Returns a string representation of the LDContext.
     *
     * For a valid LDContext, this is currently defined as being the same as the JSON representation,
     * since that is the simplest way to represent all of the Context properties. However, application
     * code should not rely on __toString() always being the same as the JSON representation. If
     * you specifically want the latter, use `json_encode()` or {@see \LaunchDarkly\LDContext::jsonSerialize()}.
     * For an invalid LDContext, __toString() returns a description of why it is invalid.
     *
     * @return string a string representation
     */
    public function __toString(): string
    {
        if ($this->isValid()) {
            return json_encode($this);
        }
        return '[invalid context: ' . $this->getError() . ']';
    }

    private function jsonSerializeSingleKind(bool $withKind): array
    {
        $ret = ['key' => $this->_key];
        if ($withKind) {
            $ret['kind'] = $this->_kind;
        }
        if ($this->_name !== null) {
            $ret['name'] = $this->_name;
        }
        if ($this->_anonymous) {
            $ret['anonymous'] = true;
        }
        if ($this->_attributes !== null) {
            $ret = array_merge($ret, $this->_attributes);
        }
        if ($this->_privateAttributes !== null) {
            $ret['_meta'] = [
                'privateAttributes' => array_map(fn (AttributeReference $a) => $a->getPath(), $this->_privateAttributes)
            ];
        }
        return $ret;
    }

    private function makeInvalid(string $error): void
    {
        $this->_error = $error;
        // No other properties can be set if there's an error, but we'll still ensure that all
        // properties that are normally non-null have non-null values, to make null reference
        // errors less likely.
        $this->_key = '';
        $this->_kind = '';
        $this->_anonymous = false;
    }

    private static function createWithError(string $error): LDContext
    {
        return new LDContext(null, '', null, false, null, null, null, $error);
    }

    private static function decodeJsonSingleKind(array $o, ?string $kind): LDContext
    {
        $b = self::builder('');
        if ($kind !== null) {
            $b->kind($kind);
        }
        foreach ($o as $k => $v) {
            if ($k === '_meta') {
                if ($v === null) {
                    continue;
                }
                if (!is_array($v) && !is_object($v)) {
                    throw self::parsingBadTypeError($k);
                }
                $a = (array)$v; // it might have been parsed as an object
                $private = $a['privateAttributes'] ?? null;
                if ($private !== null) {
                    if (!is_array($private)) {
                        throw self::parsingBadTypeError('privateAttributes');
                    }
                    foreach ($private as $p) {
                        $b->private($p);
                    }
                }
            } else {
                if (!$b->trySet($k, $v)) {
                    throw self::parsingBadTypeError($k);
                }
                if ($k === 'kind') {
                    $kind = $v;
                }
            }
        }
        if ($kind === '' || $kind === null) {
            // the builder's validation wouldn't catch this because the builder has a default kind of "user"
            return self::createWithError(self::ERR_KIND_CANNOT_BE_EMPTY);
        }
        return $b->build();
    }

    private static function validateKind(string $kind): ?string
    {
        switch ($kind) {
            case 'kind':
                return self::ERR_KIND_CANNOT_BE_KIND;
            case 'multi':
                return self::ERR_KIND_MULTI_FOR_SINGLE;
            default:
                if (preg_match('/[^-a-zA-Z0-9._]/', $kind)) {
                    return self::ERR_KIND_INVALID_CHARS;
                }
                return null;
        }
    }

    private static function escapeKeyForFullyQualifiedKey(string $s): string
    {
        // When building a fully-qualified key, ':' and '%' are percent-escaped; we do not use a full
        // URL-encoding function because implementations of this are inconsistent across platforms.
        return str_replace(':', '%3A', str_replace('%', '%25', $s));
    }

    private static function parsingBadTypeError(string $property): \InvalidArgumentException
    {
        return new \InvalidArgumentException("invalid context JSON: $property had an invalid type");
    }
}
