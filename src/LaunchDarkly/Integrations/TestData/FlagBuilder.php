<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations\TestData;

define('TRUE_VARIATION_INDEX', 0);
define('FALSE_VARIATION_INDEX', 1);


/**
 * A builder for feature flag configurations to be used with {@see \LaunchDarkly\Integrations\TestData}.
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
     * vise versa.
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
        $to->_targets = $this->_targets;
        $to->_rules = $this->_rules;

        return $to;
    }

    /**
     * Determines if the current flag is a boolean flag.
     *
     * @return bool true if flag is a boolean flag, false otherwise
     */
    private function _isBooleanFlag(): bool
    {
        return (count($this->_variations) === 2
            && $this->_variations[TRUE_VARIATION_INDEX] === true
            && $this->_variations[FALSE_VARIATION_INDEX] === false);
    }

    /**
     * A shortcut for setting the flag to use the standard boolean configuration.
     *
     * This is the default for all new flags created with
     *      `$ldclient->integrations->test_data->TestData->flag()`.
     *
     * The flag will have two variations, `true` and `false` (in that order);
     * it will return `false` whenever targeting is off, and `true` when targeting is on
     * if no other settings specify otherwise.
     *
     * @return FlagBuilder the flag builder
     */
    public function booleanFlag(): FlagBuilder
    {
        if ($this->_isBooleanFlag()) {
            return $this;
        } else {
            return ($this->variations(true, false)
                ->fallthroughVariation(TRUE_VARIATION_INDEX)
                ->offVariation(FALSE_VARIATION_INDEX));
        }
    }

    /**
     * Sets targeting to be on or off for this flag.
     *
     * The effect of this depends on the rest of the flag configuration,
     * just as it does on the real LaunchDarkly dashboard. In the default
     * configuration that you get from calling TestData->flag() with a
     * new flag key, the flag will return false whenever targeting is
     * off, and true when targeting is on.
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
        } else {
            $this->_fallthroughVariation = $variation;
            return $this;
        }
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
     */
    public function variationForAllUsers(bool|int $variation): FlagBuilder
    {
        if (is_bool($variation)) {
            return $this->booleanFlag()->variationForAllUsers($this->variationForBoolean($variation));
        } else {
            return $this->on(true)->clearRules()->clearUserTargets()->fallthroughVariation($variation);
        }
    }

    /**
     * Sets the flag to always return the specified variation value for all users.
     *
     * The value may be of any JSON type. This method changes the flag to have
     * only a single variation, which is this value, and to return the same
     * variation regardless of whether targeting is on or off. Any existing
     * targets or rules are removed.
     *
     * @param mixed $value the desired value to be returned for all users
     * @return FlagBuilder the flag builder
     */
    public function valueForAllUsers(mixed $value): FlagBuilder
    {
        $json = json_decode(json_encode($value), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->variations($json);
            return $this->variationForAllUsers(0);
        } else {
            return $this;
        }
    }

    /**
     * Sets the flag to return the specified variation for a specific user key when targeting
     * is on.
     *
     * This has no effect when targeting is turned off for the flag.
     *
     * @param string $userKey string a user key
     * @param bool|int $variation `true` or `false` or the desired variation index to return:
     *                  `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     */
    public function variationForUser(string $userKey, bool|int $variation): FlagBuilder
    {
        if (is_bool($variation)) {
            return $this->booleanFlag()
                        ->variationForUser($userKey, $this->variationForBoolean($variation));
        } else {
            $variationIndex = $variation;
            $targets = $this->_targets;

            $variationKeys = array_keys($this->_variations);
            foreach ($variationKeys as $idx) {
                if ($idx == $variationIndex) {
                    $targetForVariation = $targets[$idx] ?? [];

                    if (!in_array($userKey, $targetForVariation)) {
                        $targetForVariation[] = $userKey;
                    }
                    $this->_targets[$idx] = $targetForVariation;
                } elseif (array_key_exists($idx, $targets)) {
                    $targetForVariation = $targets[$idx];
                    $userKeyIdx = array_search($userKey, $targetForVariation);
                    // $userKeyIdx can be 0,1,2,3 etc or false if not found.
                    // Needs a strict check to ensure it doesn't eval to true
                    // when index === 0
                    if ($userKeyIdx !== false) {
                        array_splice($targetForVariation, $userKeyIdx, 1);
                        $this->_targets[$idx] = $targetForVariation;
                    }
                }
            }
            return $this;
        }
    }

    /**
     * Changes the allowable variation values for the flag.
     *
     * The value may be of any valid JSON type. For instance, a boolean flag
     * normally has true, false; a string-valued flag might have
     * 'red', 'green'; etc.
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
        $this->_variations = $variations;
        return $this;
    }

    /**
     * Starts defining a flag rule, using the "is one of" operator.
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
        $flagRuleBuilder = new FlagRuleBuilder($this);
        return $flagRuleBuilder->andMatch($attribute, ...$values);
    }

    /**
     * Starts defining a flag rule, using the "is not one of" operator.
     *
     * For example, this creates a rule that returns `true` if the name
     * is neither "Saffron" nor "Bubble":
     *
     *    $testData->flag("flag")
     *             ->ifNotMatch("name", "Saffron", "Bubble")
     *             ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder call `thenReturn(boolean)` or
     *   `thenReturn(int)` to finish the rule, or add more tests with another
     *   method like `andMatch()`
     */
    public function ifNotMatch(string $attribute, mixed ...$values): FlagRuleBuilder
    {
        $flagRuleBuilder = new FlagRuleBuilder($this);
        return $flagRuleBuilder->andNotMatch($attribute, ...$values);
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
     * Removes any existing user targets from the flag. This undoes the effect of methods like
     * `variationForUser(string, boolean)`.
     *
     * @return FlagBuilder the same builder
     */
    public function clearUserTargets(): FlagBuilder
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
            'salt' => null,
        ];

        $baseFlagObject['offVariation'] = $this->_offVariation;
        $baseFlagObject['fallthrough'] = [
            'variation' => $this->_fallthroughVariation
        ];

        $targets = [];
        foreach ($this->_targets as $varIndex => $userKeys) {
            $targets[$varIndex] = [
                'variation' => $varIndex,
                'values' => $userKeys
            ];
        }

        // used to reset index
        ksort($targets);
        $targets = array_values($targets);

        $baseFlagObject['targets'] = $targets;

        $baseFlagObject['rules'] = [];

        foreach ($this->_rules as $idx => $rule) {
            $baseFlagObject['rules'][] = $rule->build($idx);
        }

        $baseFlagObject['deleted'] = false;

        return $baseFlagObject;
    }
}
