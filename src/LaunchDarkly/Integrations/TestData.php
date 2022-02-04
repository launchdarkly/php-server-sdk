<?php

namespace LaunchDarkly\Integrations;

use \LaunchDarkly\FeatureRequester;
use \LaunchDarkly\Impl\Model\FeatureFlag;
use \LaunchDarkly\Impl\Model\Segment;

define('TRUE_VARIATION_INDEX', 0);
define('FALSE_VARIATION_INDEX', 1);

class TestData implements FeatureRequester
{

    /** @var array */
    protected $_flagBuilders;
    /** @var array */
    protected $_currentFlags;

    public function __construct()
    {
        $this->_flagBuilders = [];
        $this->_currentFlags = [];
    }


    /**
     * Gets the configuration for a specific feature flag.
     *
     * @param string $key feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag
    {
        if (array_key_exists($key, $this->_currentFlags)) {
            return $this->_currentFlags[$key];
        }
        return null;
    }

    /**
     * Gets the configuration for a specific user segment.
     *
     * @param string $key segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment(string $key): ?Segment
    {
        return null;
    }

    /**
     * Gets all feature flags.
     *
     * @return array<string, FeatureFlag>|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array
    {
        return $this->_currentFlags;
    }

    /**
     * Creates a new instance of the test data source
     *
     * @return TestData a new configurable test data source
     */
    public function dataSource()
    {
        return new TestData();
    }


    /**
     * Creates or copies a `FlagBuilder` for building a test flag configuration.
     *
     * If this flag key has already been defined in this `TestData` instance, then the builder
     * starts with the same configuration that was last provided for this flag.
     *
     * Otherwise, it starts with a new default configuration in which the flag has `True` and
     * `False` variations, is `True` for all users when targeting is turned on and
     * `False` otherwise, and currently has targeting turned on. You can change any of those
     * properties, and provide more complex behavior, using the `FlagBuilder` methods.
     *
     * Once you have set the desired configuration, pass the builder to `update`.
     *
     * @param string $key the flag key
     * @return FlagBuilder the flag configuration builder object
     */
    public function flag(string $key): FlagBuilder
    {
        if (in_array($key, $this->_flagBuilders) && $this->_flagBuilders[$key]) {
            return $this->_flagBuilders[$key]->copy();
        } else {
            $flagBuilder = new FlagBuilder($key);
            return $flagBuilder->booleanFlag();
        }
    }

    /**
     * Updates the test data with the specified flag configuration.
     *
     * This has the same effect as if a flag were added or modified on the LaunchDarkly dashboard.
     * It immediately propagates the flag change to any `LDClient` instance(s) that you have
     * already configured to use this `TestData`. If no `LDClient` has been started yet,
     * it simply adds this flag to the test data which will be provided to any `LDClient` that
     * you subsequently configure.
     *
     * Any subsequent changes to this `FlagBuilder` instance do not affect the test data,
     * unless you call `update(FlagBuilder)` again.
     *
     * @param FlagBuilder $flagBuilder a flag configuration builder
     * @return TestData the same `TestData` instance
     */
    public function update(FlagBuilder $flagBuilder): TestData
    {
        $key = $flagBuilder->getKey();
        $oldVersion = 0;

        if (array_key_exists($key, $this->_currentFlags)) {
            $oldFlag = $this->_currentFlags[$key];
            if ($oldFlag) {
                $oldVersion = $oldFlag['version'];
            }
        }

        $newFlag = $flagBuilder->build($oldVersion + 1);
        $newFeatureFlag = FeatureFlag::decode($newFlag);
        $this->_currentFlags[$key] = $newFeatureFlag;
        $this->_flagBuilders[$key] = $flagBuilder->copy();
        return $this;
    }
}


/**
 * A builder for feature flag configurations to be used with {@see \LaunchDarkly\Integrations\TestData}.
 *
 * @see TestData::flag()
 * @see TestData::update()
 */
class FlagBuilder
{

    /** @var string */
    protected $_key;
    /** @var boolean */
    protected $_on;
    /** @var array */
    protected $_variations;
    /** @var int|null */
    protected $_offVariation;
    /** @var int|null */
    protected $_fallthroughVariation;
    /** @var array */
    protected $_targets;
    /** @var array */
    protected $_rules;

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
    public function getKey()
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
    public function copy()
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
     * @return boolean true if flag is a boolean flag, false otherwise
     */
    private function _isBooleanFlag()
    {
        return (count($this->_variations) == 2
            && $this->_variations[TRUE_VARIATION_INDEX] == true
            && $this->_variations[FALSE_VARIATION_INDEX] == false);
    }

    /**
     * A shortcut for setting the flag to use the standard boolean configuration.
     *
     * This is the default for all new flags created with
     *      `$ldclient->integrations->test_data->TestData->flag()`.
     *
     * The flag will have two variations, `True` and `False` (in that order);
     * it will return `False` whenever targeting is off, and `True` when targeting is on
     * if no other settings specify otherwise.
     *
     * @return FlagBuilder the flag builder
     */
    public function booleanFlag()
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
    public function on($on)
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
    public function fallthroughVariation($variation)
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
    public function offVariation($variation)
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
     * The variation is specified, Targeting is switched on, and any existing targets or rules are removed.
     * The fallthrough variation is set to the specified value. The off variation is left unchanged.
     *
     * If the flag was previously configured with other variations and the variation specified is a boolean,
     * this also changes it to a boolean flag.
     *
     * @param bool|int $variation `True` or `False` or the desired variation index to return:
     *                  `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     */
    public function variationForAllUsers($variation)
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
    public function valueForAllUsers($value)
    {
        $json = json_decode(json_encode($value), true);
        // TODO: Is there some error to return if
        // $value is not json decode-able?
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->variations([$json]);
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
     * The variation is specified by number, out of whatever variation values have already been
     * defined.
     *
     * @param string $userKey string a user key
     * @param int|bool $variation the desired variation to be returned for this
     * user when targeting is on: 0 for the first, 1 for the second, etc.
     * @return FlagBuilder the flag builder
     */
    public function variationForUser(string $userKey, $variation)
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
                    $targetForVariation = [];
                    if (array_key_exists($idx, $targets)) {
                        $targetForVariation = $targets[$idx];
                    }

                    if (!in_array($userKey, $targetForVariation)) {
                        array_push($targetForVariation, $userKey);
                    }
                    $this->_targets[$idx] = $targetForVariation;
                } else {
                    if (array_key_exists($idx, $targets)) {
                        $targetForVariation = $targets[$idx];
                        $userKeyIdx = array_search($userKey, $targetForVariation);
                        // $userKeyIdx can be 0,1,2,3 etc or false if not found.
                        // Needs a strict check to ensure it doesn't eval to true
                        // when index === 0
                        if ($userKeyIdx !== false) {
                            unset($targetForVariation[$userKeyIdx]);
                            $targetForVariation = array_values($targetForVariation);
                            $this->_targets[$idx] = $targetForVariation;
                        }
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
     * normally has True, False; a string-valued flag might have
     * 'red', 'green'; etc.
     *
     * Example: A single variation
     *
     *     $td->flag('new-flag')->variations(True)
     *
     * Example: Multiple variations
     *
     *     $td->flag('new-flag')->variations('red', 'green', 'blue')
     *
     * @param array $variations the the desired variations
     * @return FlagBuilder the flag builder object
     */
    public function variations(...$variations): FlagBuilder
    {
        if (count($variations) == 1) {
            if (is_array($variations[0])) {
                $variations = $variations[0];
            }
        }
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
     * @param mixed $values values to compare to
     * @return FlagRuleBuilder call `thenReturn(boolean)` or
     *   `thenReturn(int)` to finish the rule, or add more tests with another
     *   method like `andMatch()`
     */
    public function ifMatch($attribute, ...$values)
    {
        $flagRuleBuilder = new FlagRuleBuilder($this);
        return $flagRuleBuilder->andMatch($attribute, $values);
    }

    /**
     * Starts defining a flag rule, using the "is not one of" operator.
     *
     * For example, this creates a rule that returns `true` if the name
     * is neither "Saffron" nor "Bubble":
     *
     *     testData->flag("flag")
     *             ->ifNotMatch("name", "Saffron", "Bubble")
     *             ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed $values values to compare to
     * @return FlagRuleBuilder call `thenReturn(boolean)` or
     *   `thenReturn(int)` to finish the rule, or add more tests with another
     *   method like `andMatch()`
     */
    public function ifNotMatch(string $attribute, mixed $values): FlagRuleBuilder
    {
        $flagRuleBuilder = new FlagRuleBuilder($this);
        return $flagRuleBuilder->andNotMatch($attribute, $values);
    }

    /**
     * @param FlagRuleBuilder $flagRuleBuilder
     * @return FlagBuilder the flag builder
     */
    public function addRule($flagRuleBuilder): FlagBuilder
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
            'variations' => $this->_variations
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
            array_push($baseFlagObject['rules'], $rule->build($idx));
        }

        $baseFlagObject['deleted'] = false;

        return $baseFlagObject;
    }
}

/**
 * A builder for feature flag rules to be used with {@see \LaunchDarkly\Integrations\FlagBuilder}.
 *
 * In the LaunchDarkly model, a flag can have any number of rules,
 * and a rule can have any number of clauses. A clause is an individual
 * test such as "name is 'X'". A rule matches a user if all of the
 * rule's clauses match the user.
 *
 * To start defining a rule, use one of the flag builder's matching methods such as
 * `ifMatch('country', 'us'...)`. This defines the first clause for the rule.
 * Optionally, you may add more clauses with the rule builder's methods such as
 * `andMatch('age', '20'...)`. Finally, call `thenReturn(boolean)` or
 * `thenReturn(int)` to finish defining the rule.
 */
class FlagRuleBuilder
{

    /** @var FlagBuilder */
    protected $_flagBuilder;
    /** @var array */
    protected $_clauses;
    /** @var int|null */
    protected $_variation;


    public function __construct(FlagBuilder $flagBuilder)
    {
        $this->_flagBuilder = $flagBuilder;
        $this->_clauses = [];
        $this->_variation = null;
    }

    /**
     * Adds another clause, using the "is one of" operator.
     *
     * For example, this creates a rule that returns `true` if
     * the name is "Patsy" and the country is "gb":
     *
     *     $testData->flag("flag")
     *              ->ifMatch("NAME", "Patsy")
     *              ->andMatch("COUNTRY", "gb")
     *              ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed $values values to compare to
     * @return FlagRuleBuilder the rule builder
     */
    public function andMatch(string $attribute, $values)
    {
        $newClause = [
            "attribute" => $attribute,
            "operator" => 'in',
            // TODO: does values need to be type checked
            // or put into a list?
            "values" => $values,
            "negate" => false,
        ];
        array_push($this->_clauses, $newClause);
        return $this;
    }

    /**
     * Adds another clause, using the "is not one of" operator.
     *
     * For example, this creates a rule that returns `true` if
     * the name is "Patsy" and the country is not "gb":
     *
     *     testData.flag("flag")
     *         ->ifMatch("name", "Patsy")
     *         ->andNotMatch("country", "gb")
     *         ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed $values values to compare to
     * @return FlagRuleBuilder the rule builder
     */
    public function andNotMatch(string $attribute, ...$values)
    {
        $newClause = [
            "attribute" => $attribute,
            "operator" => 'in',
            // TODO: does values need to be type checked
            // or put into a list?
            "values" => $values,
            "negate" => true,
        ];
        array_push($this->_clauses, $newClause);
        return $this;
    }

    /**
     * Finishes defining the rule, specifying the result
     * value as a boolean or variation index.
     *
     * @param bool|int $variation the value to return if the rule matches the user
     * @return FlagBuilder the flag builder
     */
    public function thenReturn($variation)
    {
        if (is_bool($variation)) {
            $this->_flagBuilder->booleanFlag();
            return $this->thenReturn($this->_flagBuilder->variationForBoolean($variation));
        } else {
            $this->_variation = $variation;
            $this->_flagBuilder->addRule($this);
            return $this->_flagBuilder;
        }
    }

    /**
     * Creates an associative array representation of the flag
     *
     * @param int $id the rule id
     * @return array the array representation of the flag
     */
    public function build(int $id)
    {
        return [
            "id" => "rule$id",
            "variation" => $this->_variation,
            "clauses" => $this->_clauses
        ];
    }
}
