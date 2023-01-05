<?php

declare(strict_types=1);

namespace LaunchDarkly;

use LaunchDarkly\Types\AttributeReference;

/**
 * A mutable object that uses the builder pattern to specify properties for LDContext.
 *
 * Use this type if you need to construct a context that has only a single kind. To define a
 * multi-context, use {@see \LaunchDarkly\LDContext::createMulti()} or
 * {@see \LaunchDarkly\LDContext::multiBuilder()}.
 *
 * Obtain an instance of LDContextBuilder by calling {@see \LaunchDarkly\LDContext::builder()}.
 * Then, call setter methods such as {@see \LaunchDarkly\LDContextBuilder::name()} or
 * {@see \LaunchDarkly\LDContextBuilder::set()} to specify any additional attributes. Then, call
 * {@see \LaunchDarkly\LDContextBuilder::build()} to create the context. LDContextBuilder setters
 * return a reference to the same builder, so calls can be chained:
 * ```php
 *     $context = LDContext::builder('user-key')
 *         ->name('my-name')
 *         ->set('country', 'us')
 *         ->build();
 * ```
 *
 * @see \LaunchDarkly\LDContext
 */
class LDContextBuilder
{
    private string $_key;
    private ?string $_kind = null;
    private ?string $_name = null;
    private bool $_anonymous = false;
    private ?array $_attributes = null;
    /** @var AttributeReference[]|null */
    private ?array $_privateAttributes = null;

    /**
     * Constructs a new builder.
     *
     * @param string key the context key
     * @return LDContextBuilder
     */
    public function __construct(string $key)
    {
        $this->_key = $key;
    }

    /**
     * Creates an LDContext from the current builder properties.
     *
     * The LDContext is immutable and will not be affected by any subsequent actions on the builder.
     *
     * It is possible to specify invalid attributes for an LDContextBuilder, such as an empty key.
     * Instead of throwing an exception, the LDContextBuilder always returns an LDContext and
     * you can check {@see \LaunchDarkly\LDContext::isValid()} or {@see \LaunchDarkly\LDContext::getError()}
     * to see if it has an error. See {@see \LaunchDarkly\LDContext::isValid()} for more information
     * about invalid conditions. If you pass an invalid LDContext to an SDK method, the SDK will
     * detect this and will log a description of the error.
     *
     * @return LDContext a new {@see \LaunchDarkly\LDContext}
     */
    public function build(): LDContext
    {
        return new LDContext(
            $this->_kind ?: LDContext::DEFAULT_KIND,
            $this->_key,
            $this->_name,
            $this->_anonymous,
            $this->_attributes,
            $this->_privateAttributes,
            null,
            null
        );
    }

    /**
     * Sets the context's key attribute.
     *
     * Every context has a key, which is always a string. It cannot be an empty string, but
     * there are no other restrictions on its value.
     *
     * The key attribute can be referenced by flag rules, flag target lists, and segments.
     *
     * @param string $key the context key
     * @return LDContextBuilder the builder
     * @see \LaunchDarkly\LDContext::getKey()
     */
    public function key(string $key): LDContextBuilder
    {
        $this->_key = $key;
        return $this;
    }

    /**
     * Sets the context's kind attribute.
     *
     * Every context has a kind. Setting it to an empty string or null is equivalent to
     * {@see \LaunchDarkly\LDContext::DEFAULT_KIND} ("user"). This value is case-sensitive.
     *
     * The meaning of the context kind is completely up to the application. Validation rules are
     * as follows:
     *
     * - It may only contain letters, numbers, and the characters `.`, `_`, and `-`.
     * - It cannot equal the literal string "kind".
     * - For a single context, it cannot equal "multi".
     *
     * @param string $kind the context kind
     * @return LDContextBuilder the builder
     * @see \LaunchDarkly\LDContext::getKind()
     */
    public function kind(string $kind): LDContextBuilder
    {
        $this->_kind = $kind;
        return $this;
    }

    /**
     * Sets the context's name attribute.
     *
     * This attribute is optional. It has the following special rules:
     *
     * - Unlike most other attributes, it is always a string if it is specified.
     * - The LaunchDarkly dashboard treats this attribute as the preferred display name for contexts.
     *
     * @param ?string $name the name attribute (null to unset the attribute)
     * @return LDContextBuilder the builder
     * @see \LaunchDarkly\LDContext::getName()
     */
    public function name(?string $name): LDContextBuilder
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Sets whether the context is only intended for flag evaluations and should not be
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
     * @param bool $anonymous true if the context should be excluded from the LaunchDarkly database
     * @return LDContextBuilder the builder
     * @see \LaunchDarkly\LDContext::isAnonymous()
     */
    public function anonymous(bool $anonymous): LDContextBuilder
    {
        $this->_anonymous = $anonymous;
        return $this;
    }

    /**
     * Sets the value of any attribute for the context.
     *
     * This includes only attributes that are addressable in evaluations-- not metadata
     * such as {@see \LaunchDarkly\LDContextBuilder::private()}. If `attributeName`
     * is `'private'`, you will be setting an attribute with that name which you can
     * use in evaluations or to record data for your own purposes, but it will be unrelated
     * to {@see \LaunchDarkly\LDContextBuilder::private()}.
     *
     * The allowable types for context attributes are equivalent to JSON types: boolean,
     * number, string, array, or object. For all attribute names that do not have special
     * meaning to LaunchDarkly, you may use any of those types. Values of different JSON
     * types are always treated as different values: for instance, the number 1 is not the
     * same as the string "1".
     *
     * The following attribute names have special restrictions on their value types, and
     * any value of an unsupported type will be ignored (leaving the attribute unchanged):
     *
     * - `kind`, `key`: Must be a string. See {@see \LaunchDarkly\LDContextBuilder::kind()}
     * and {@see \LaunchDarkly\LDContextBuilder::key()}.
     * - `name`: Must be a string or null. See {@see \LaunchDarkly\LDContextBuilder::name()}.
     * - `anonymous`: Must be a boolean. See {@see \LaunchDarkly\LDContextBuilder::anonymous()}.
     *
     * The attribute name "_meta" is not allowed, because it has special meaning in the
     * JSON schema for contexts; any attempt to set an attribute with this name has no
     * effect.
     *
     * Values that are JSON arrays or objects have special behavior when referenced in
     * flag/segment rules.
     *
     * A value of `null` is equivalent to removing any current non-default value of the
     * attribute. Null is not a valid attribute value in the LaunchDarkly model; any
     * expressions in feature flags that reference an attribute with a null value will
     * behave as if the attribute did not exist.
     *
     * @param string $attributeName the attribute name to set
     * @param mixed $value the value to set
     * @return LDContextBuilder the builder
     * @see \LaunchDarkly\LDContext::get()
     * @see \LaunchDarkly\LDContextBuilder::trySet()
     */
    public function set(string $attributeName, mixed $value): LDContextBuilder
    {
        $this->trySet($attributeName, $value);
        return $this;
    }

    /**
     * Same as set(), but returns a boolean indicating whether the attribute was successfully set.
     *
     * @param string $attributeName the attribute name to set
     * @param mixed $value the value to set
     * @return bool true if successful; false if the name was invalid or the value was not an
     *   allowed type for that attribute
     * @see \LaunchDarkly\LDContextBuilder::set()
     */
    public function trySet(string $attributeName, mixed $value): bool
    {
        switch ($attributeName) {
            case 'key':
                if (!is_string($value)) {
                    return false;
                }
                $this->_key = $value;
                break;
            case 'kind':
                if (!is_string($value)) {
                    return false;
                }
                $this->_kind = $value;
                break;
            case 'name':
                if ($value != null && !is_string($value)) {
                    return false;
                }
                $this->_name = $value;
                break;
            case 'anonymous':
                if (!is_bool($value)) {
                    return false;
                }
                $this->_anonymous = $value;
                break;
            default:
                if ($this->_attributes === null) {
                    $this->_attributes = [];
                }
                if ($value === null) {
                    unset($this->_attributes[$attributeName]);
                } else {
                    $this->_attributes[$attributeName] = $value;
                }
                break;
        }
        return true;
    }

    /**
     * Designates any number of LDContext attributes, or properties within them, as private: that is,
     * their values will not be sent to LaunchDarkly.
     *
     * Each parameter can be either a simple attribute name, or a slash-delimited path (in the format
     * defined by {@see \LaunchDarkly\Types\AttributeReference}) referring to a JSON object property
     * within an attribute.
     *
     * @param array $attributeRefs attribute names or references to mark as private
     * @return LDContextBuilder the builder
     * @see \LaunchDarkly\LDContext::getPrivateAttributes()
     */
    public function private(string|AttributeReference ...$attributeRefs): LDContextBuilder
    {
        if (count($attributeRefs) === 0) {
            return $this;
        }
        if ($this->_privateAttributes === null) {
            $this->_privateAttributes = [];
        }
        foreach ($attributeRefs as $p) {
            if (is_string($p)) {
                $parsed = AttributeReference::fromPath($p);
            } elseif ($p instanceof AttributeReference) {
                $parsed = $p;
            } else {
                continue;
            }
            if ($parsed->getError() === null) {
                $this->_privateAttributes[] = $parsed;
            }
        }
        return $this;
    }
}
