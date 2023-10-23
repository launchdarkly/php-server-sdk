<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations\TestData;

use LaunchDarkly\LDContext;

define('TRUE_VARIATION_INDEX', 0);
define('FALSE_VARIATION_INDEX', 1);


/**
 * A builder for feature flag configurations to be used with TestData.
 *
 * @see \LaunchDarkly\Integrations\TestData::flag()
 * @see \LaunchDarkly\Integrations\TestData::update()
 */
class FlagBuilder
{
    protected string $_key;
    protected bool $_on;
    protected array $_variations;
    protected ?int $_offVariation;
    protected ?int $_fallthroughVariation;
    protected ?MigrationSettingsBuilder $_migrationSettingsBuilder;
    protected ?int $_samplingRatio;
    protected bool $_excludeFromSummaries;

    // In _targets, each key is a context kind, and the value is another associative array where the key is a
    // variation index and the value is an array of context keys.
    protected array $_targets;

    protected array $_rules;

    public function __construct(string $key)
    {
        $this->_key = $key;
        $this->_on = true;
        $this->_variations = [];
        $this->_offVariation = null;
        $this->_fallthroughVariation = null;
        $this->_targets = [];
        $this->_rules = [];
        $this->_samplingRatio = null;
        $this->_excludeFromSummaries = false;
        $this->_migrationSettingsBuilder = null;
    }

    /**
     * Returns the key of the Flag Builder
     *
     * @return string the key of the flag builder
     */
    public function getKey(): string
    {
        return $this->_key;
    }

    /**
     * Creates a deep copy of the flag builder. Subsequent updates to
     * the original FlagBuilder object will not update the copy and
     * vice versa.
     *
     * @return FlagBuilder A copy of the flag builder object
     */
    public function copy(): FlagBuilder
    {
        $to = new FlagBuilder($this->_key);

        $to->_on = $this->_on;
        $to->_variations = $this->_variations;
        $to->_offVariation = $this->_offVariation;
        $to->_fallthroughVariation = $this->_fallthroughVariation;
        $to->_targets = [];
        foreach ($this->_targets as $k => $v) { // this array contains arrays so must be explicitly deep-copied
            $to->_targets[$k] = $v;
        }
        $to->_rules = $this->_rules;
        $to->_samplingRatio = $this->_samplingRatio;
        $to->_excludeFromSummaries = $this->_excludeFromSummaries;
        $to->_migrationSettingsBuilder = $this->_migrationSettingsBuilder;

        return $to;
    }

    /**
     * A shortcut for setting the flag to use the standard boolean configuration.
     *
     * This is the default for all new flags created with {@see
     * \LaunchDarkly\Integrations\TestData::flag()}.
     *
     * The flag will have two variations, `true` and `false` (in that order);
     * it will return `false` whenever targeting is off, and `true` when targeting is on
     * if no other settings specify otherwise.
     *
     * @return FlagBuilder the flag builder
     */
    public function booleanFlag(): FlagBuilder
    {
        if (count($this->_variations) === 2
            && $this->_variations[TRUE_VARIATION_INDEX] === true
            && $this->_variations[FALSE_VARIATION_INDEX] === false) {
            return $this;
        }
        return ($this->variations(true, false)
            ->fallthroughVariation(TRUE_VARIATION_INDEX)
            ->offVariation(FALSE_VARIATION_INDEX));
    }

    /**
     * Sets targeting to be on or off for this flag.
     *
     * The effect of this depends on the rest of the flag configuration, just as it does on the
     * real LaunchDarkly dashboard. In the default configuration that you get from calling {@see
     * \LaunchDarkly\Integrations\TestData::flag()} with a new flag key, the flag will return false
     * whenever targeting is off, and true when targeting is on.
     *
     * @param bool $on true if targeting should be on
     * @return FlagBuilder the flag builder object
     */
    public function on(bool $on): FlagBuilder
    {
        $this->_on = $on;
        return $this;
    }

    /**
     * Specifies the fallthrough variation. The fallthrough is the value
     * that is returned if targeting is on and the user was not matched by a more specific
     * target or rule.
     *
     * If the flag was previously configured with other variations and the variation
     * specified is a boolean, this also changes it to a boolean flag.
     *
     * @param bool|int $variation true or false or the desired fallthrough
     *                 variation index `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     */
    public function fallthroughVariation(bool|int $variation): FlagBuilder
    {
        if (is_bool($variation)) {
            $this->booleanFlag()->_fallthroughVariation = $this->variationForBoolean($variation);
            return $this;
        }
        $this->_fallthroughVariation = $variation;
        return $this;
    }

    public function migrationSettings(MigrationSettingsBuilder $builder): FlagBuilder
    {
        $this->_migrationSettingsBuilder = $builder;
        return $this;
    }

    /**
     * Control the rate at which events from this flag will be sampled.
     */
    public function samplingRatio(int $samplingRatio): FlagBuilder
    {
        $this->_samplingRatio = $samplingRatio;
        return $this;
    }

    /**
     * Control whether or not this flag should should be included in flag summary counts.
     */
    public function excludeFromSummaries(bool $excludeFromSummaries): FlagBuilder
    {
        $this->_excludeFromSummaries = $excludeFromSummaries;
        return $this;
    }

    /**
     * Specifies the off variation for a boolean flag or index of variation.
     * This is the variation that is returned whenever targeting is off.
     *
     * @param bool|int $variation either boolean variation or integer index of variation
     * @return FlagBuilder the flag builder
     */
    public function offVariation(bool|int $variation): FlagBuilder
    {
        if (is_bool($variation)) {
            $this->booleanFlag()->_offVariation = $this->variationForBoolean($variation);
            return $this;
        }
        $this->_offVariation = $variation;
        return $this;
    }

    /**
     * Sets the flag to always return the specified variation for all users.
     *
     * The variation is specified, targeting is switched on, and any existing targets or rules are removed.
     * The fallthrough variation is set to the specified value. The off variation is left unchanged.
     *
     * If the flag was previously configured with other variations and the variation specified is a boolean,
     * this also changes it to a boolean flag.
     *
     * @param bool|int $variation `true` or `false` or the desired variation index to return:
     *                  `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     * @see \LaunchDarkly\Integrations\TestData\FlagBuilder::valueForAll()
     */
    public function variationForAll(bool|int $variation): FlagBuilder
    {
        if (is_bool($variation)) {
            return $this->booleanFlag()->variationForAll($this->variationForBoolean($variation));
        }
        return $this->on(true)->clearRules()->clearTargets()->fallthroughVariation($variation);
    }

    /**
     * Sets the flag to always return the specified variation value for all users.
     *
     * The value may be of any JSON-serializable type. This method changes the flag to have
     * only a single variation, which is this value, and to return the same variation regardless
     * of whether targeting is on or off. Any existing targets or rules are removed.
     *
     * @param mixed $value the desired value to be returned for all users
     * @return FlagBuilder the flag builder
     * @see \LaunchDarkly\Integrations\TestData\FlagBuilder::variationForAll()
     */
    public function valueForAll(mixed $value): FlagBuilder
    {
        return $this->variations($value)->variationForAll(0);
    }

    /**
     * Sets the flag to return the specified variation for a specific user key when targeting
     * is on.
     *
     * This is a shortcut for calling {@see \LaunchDarkly\Integrations\TestData\FlagBuilder::variationForKey()}
     * with `LDContext::DEFAULT_KIND` as the context kind.
     *
     * This has no effect when targeting is turned off for the flag.
     *
     * @param string $userKey string a user key
     * @param bool|int $variation `true` or `false` or the desired variation index to return:
     *                  `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     * @see \LaunchDarkly\Integrations\TestData\FlagBuilder::variationForKey()
     */
    public function variationForUser(string $userKey, bool|int $variation): FlagBuilder
    {
        return $this->variationForKey(LDContext::DEFAULT_KIND, $userKey, $variation);
    }

    /**
     * Sets the flag to return the specified boolean variation for a specific context, identified
     * by context kind and key, when targeting is on.
     *
     * This has no effect when targeting is turned off for the flag.
     *
     * @param string contextKind the context kind
     * @param string $key string the context key
     * @param bool|int $variation `true` or `false` or the desired variation index to return:
     *                  `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     */
    public function variationForKey(string $contextKind, string $key, bool|int $variation): FlagBuilder
    {
        if (is_bool($variation)) {
            return $this->booleanFlag()
                        ->variationForKey($contextKind, $key, $this->variationForBoolean($variation));
        }
        $variationIndex = $variation;

        $targets = $this->_targets[$contextKind] ?? [];
        foreach ($this->_variations as $idx => $value) {
            $targetsForVariation = $targets[$idx] ?? [];
            if ($idx === $variationIndex) {
                if (!in_array($key, $targetsForVariation)) {
                    $targetsForVariation[] = $key;
                    $targets[$idx] = $targetsForVariation;
                }
            } elseif (array_key_exists($idx, $targets)) {
                $foundIndex = array_search($key, $targetsForVariation);
                if ($foundIndex !== false) {
                    array_splice($targetsForVariation, $foundIndex, 1);
                    $targets[$idx] = $targetsForVariation;
                }
            }
        }
        ksort($targets); // ensures deterministic order of output
        $this->_targets[$contextKind] = $targets;
        return $this;
    }

    /**
     * Changes the allowable variation values for the flag.
     *
     * The values may be of any JSON-serializable types. For instance, a boolean flag
     * normally has true, false; a string-valued flag might have 'red', 'green'; etc.
     *
     * Example: A single variation
     *
     *     $td->flag('new-flag')->variations(true)
     *
     * Example: Multiple variations
     *
     *     $td->flag('new-flag')->variations('red', 'green', 'blue')
     *
     * @param mixed[] $variations the the desired variations
     * @return FlagBuilder the flag builder object
     */
    public function variations(mixed ...$variations): FlagBuilder
    {
        $validatedVariations = [];
        foreach ($variations as $value) {
            $json = json_decode(json_encode($value), true);
            $validatedVariations[] = $json;
        }
        $this->_variations = $validatedVariations;
        return $this;
    }

    /**
     * Starts defining a flag rule, using the "is one of" operator.
     *
     * This is a shortcut for calling {@see \LaunchDarkly\Integrations\TestData\FlagBuilder::ifMatchContext()}
     * with `LDContext::DEFAULT_KIND` as the context kind.
     *
     * For example, this creates a rule that returns `true` if the name is "Patsy" or "Edina":
     *
     *     $testData->flag("flag")
     *         ->ifMatch("name", "Patsy", "Edina")
     *         ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder call `thenReturn(boolean)` or
     *   `thenReturn(int)` to finish the rule, or add more tests with another
     *   method like `andMatch()`
     */
    public function ifMatch(string $attribute, mixed ...$values): FlagRuleBuilder
    {
        return $this->ifMatchContext(LDContext::DEFAULT_KIND, $attribute, ...$values);
    }

    /**
     * Starts defining a flag rule, using the "is one of" operator. This matching expression only
     * applies to contexts of a specific kind.
     *
     * For example, this creates a rule that returns `true` if the name attribute for the
     * "company" context is "Ella" or "Monsoon":
     *
     *     $testData->flag("flag")
     *         ->ifMatchContext("company", "name", "Ella", "Monsoon")
     *         ->thenReturn(true);
     *
     * @param string $contextKind the context kind
     * @param string $attribute the context attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder call `thenReturn(boolean)` or
     *   `thenReturn(int)` to finish the rule, or add more tests with another
     *   method like `andMatch()`
     */
    public function ifMatchContext(string $contextKind, string $attribute, mixed ...$values): FlagRuleBuilder
    {
        $flagRuleBuilder = new FlagRuleBuilder($this);
        return $flagRuleBuilder->andMatchContext($contextKind, $attribute, ...$values);
    }

    /**
     * Starts defining a flag rule, using the "is not one of" operator.
     *
     * This is a shortcut for calling {@see \LaunchDarkly\Integrations\TestData\FlagBuilder::ifNotMatchContext()}
     * with `LDContext::DEFAULT_KIND` as the context kind.
     *
     * For example, this creates a rule that returns `true` if the name is neither "Saffron" nor "Bubble":
     *
     *    $testData->flag("flag")
     *             ->ifNotMatch("name", "Saffron", "Bubble")
     *             ->thenReturn(true);
     *
     * @param string $attribute the context attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder call `thenReturn(boolean)` or
     *   `thenReturn(int)` to finish the rule, or add more tests with another
     *   method like `andMatch()`
     */
    public function ifNotMatch(string $attribute, mixed ...$values): FlagRuleBuilder
    {
        return $this->ifNotMatchContext(LDContext::DEFAULT_KIND, $attribute, ...$values);
    }

    /**
     * Starts defining a flag rule, using the "is not one of" operator.
     *
     * This is a shortcut for calling {@see \LaunchDarkly\Integrations\TestData\FlagRuleBuilder::ifMatchContext()}
     * with `ContextKind::Default` as the context kind.
     *
     * For example, this creates a rule that returns `true` if the name attribute for the
     * "company" context is neither "Pendant" nor "Sterling Cooper":
     *
     *    $testData->flag("flag")
     *             ->ifNotMatchContext("company", "name", "Pendant", "Sterling Cooper")
     *             ->thenReturn(true);
     *
     * @param string $contextKind the context kind
     * @param string $attribute the context attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder call `thenReturn(boolean)` or
     *   `thenReturn(int)` to finish the rule, or add more tests with another
     *   method like `andMatch()`
     */
    public function ifNotMatchContext(string $contextKind, string $attribute, mixed ...$values): FlagRuleBuilder
    {
        $flagRuleBuilder = new FlagRuleBuilder($this);
        return $flagRuleBuilder->andNotMatchContext($contextKind, $attribute, ...$values);
    }

    /**
     * @param FlagRuleBuilder $flagRuleBuilder
     * @return FlagBuilder the flag builder
     */
    public function addRule(FlagRuleBuilder $flagRuleBuilder): FlagBuilder
    {
        array_push($this->_rules, $flagRuleBuilder);
        return $this;
    }

    /**
     * Removes any existing rules from the flag. This undoes the effect of methods like
     * `ifMatch()`.
     *
     * @return FlagBuilder the same builder
     */
    public function clearRules(): FlagBuilder
    {
        $this->_rules = [];
        return $this;
    }

    /**
     * Removes any existing targets for individual user/context keys from the flag. This undoes the effect of
     * the `variationForUser` and `variationForKey` methods.
     *
     * @return FlagBuilder the same builder
     */
    public function clearTargets(): FlagBuilder
    {
        $this->_targets = [];
        return $this;
    }

    /**
     * Converts a boolean to the corresponding variation index.
     *
     * @param boolean $variation the boolean variation value
     * @return int the variation index for the given boolean
     */
    public static function variationForBoolean(bool $variation): int
    {
        if ($variation) {
            return TRUE_VARIATION_INDEX;
        } else {
            return FALSE_VARIATION_INDEX;
        }
    }

    /**
     * Creates a Feature Flag
     *
     * @param int $version: the version number of the rule
     * @return array the feature flag
     */
    public function build(int $version): array
    {
        $baseFlagObject = [
            'key'        => $this->_key,
            'version'    => $version,
            'on'         => $this->_on,
            'variations' => $this->_variations,

            // Fields necessary to be able to pass the result
            // of build() into FeatureFlag::decode
            'prerequisites' => [],
            'salt' => '',
        ];

        $baseFlagObject['offVariation'] = $this->_offVariation;
        $baseFlagObject['fallthrough'] = [
            'variation' => $this->_fallthroughVariation
        ];

        $targets = [];
        $contextTargets = [];
        foreach ($this->_targets as $kind => $targetsForKind) {
            foreach ($targetsForKind as $varIndex => $keys) {
                if ($kind === LDContext::DEFAULT_KIND) {
                    $targets[] = [
                        'variation' => $varIndex,
                        'values' => $keys
                    ];
                    $contextTargets[] = [
                        'contextKind' => LDContext::DEFAULT_KIND,
                        'variation' => $varIndex,
                        'values' => []
                    ];
                } else {
                    $contextTargets[] = [
                        'contextKind' => $kind,
                        'variation' => $varIndex,
                        'values' => $keys
                    ];
                }
            }
        }
        $baseFlagObject['targets'] = $targets;
        $baseFlagObject['contextTargets'] = $contextTargets;

        $baseFlagObject['rules'] = [];

        foreach ($this->_rules as $idx => $rule) {
            $baseFlagObject['rules'][] = $rule->build($idx);
        }

        $migrationSettings = $this->_migrationSettingsBuilder?->build() ?? [];
        if (!empty($migrationSettings)) {
            $baseFlagObject['migration'] = $migrationSettings;
        }

        $baseFlagObject['deleted'] = false;

        return $baseFlagObject;
    }
}
