<?php

namespace LaunchDarkly\Integrations;

define('TRUE_VARIATION_INDEX', 0);
define('FALSE_VARIATION_INDEX', 1);

class TestData {

    /** @var array */
    protected $_flagBuilders;

    public function __construct()
    {
        $this->_flagBuilders = [];
    }


   /** 
    * Creates or copies a `FlagBuilder` for building a test flag configuration.

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
    * @param string $key: the flag key
    * @return FlagBuilder the flag configuration builder object
    */
    public function flag(string $key)
    {
        try {
            //self._lock.rlock()
            if (in_array($key, $this->_flagBuilders) && $this->_flagBuilders[$key]) {
                return $this->_flagBuilders[$key]->copy();
            } else {
                $flagBuilder = new FlagBuilder($key);
                return $flagBuilder->booleanFlag();
            }
        } finally {
            //self._lock.runlock()
        }
    }

}


class FlagBuilder {

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

     * If the flag was previously configured with other variations and the variation
     * specified is a boolean, this also changes it to a boolean flag.

     * @param bool/int $variation: `True` or `False` or the desired fallthrough variation index:
     *                  `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     */ 
    public function fallthroughVariation(bool|int $variation)
    {
        if (is_bool($variation)) {
            $this->booleanFlag()->_fallthroughVariation = $this->_variationForBoolean($variation);
            return $this;
        } else {
            $this->_fallthroughVariation = $variation;
            return $this;
        }
    }
    /*  
     *  Specifies the fallthrough variation. This is the variation that is returned
     *  whenever targeting is off.
     *
     *  If the flag was previously configured with other variations and the variation
     *  specified is a boolean, this also changes it to a boolean flag.
     *
     *  @param bool/int $variation: `True` or `False` or the desired off variation index:
     *                   `0` for the first, `1` for the second, etc.
     *  @return FlagBuilder the flag builder
     */

    public function offVariation(bool|int $variation)
    {
        if (is_bool($variation)) {
            $this->booleanFlag()->_offVariation = $this->_variationForBoolean($variation);
            return $this;
        }
            $this->_offVariation = $variation;
            return $this;
        }
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
     * Determines if the current flag is a boolean flag.
     *
     * @return boolean true if flag is a boolean flag, false otherwise
     */
    private function _isBooleanFlag()
    {
        return (count($this->_variations) == 2
            && $this->_variations[TRUE_VARIATION_INDEX] == True
            && $this->_variations[FALSE_VARIATION_INDEX] == False);
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
     * @param bool/int $variation: `True` or `False` or the desired variation index to return:
     *                  `0` for the first, `1` for the second, etc.
     * @return FlagBuilder the flag builder
     */
    public function variationForAllUsers(bool|int $variation)
    {
        if (is_bool($variation)) {
            return $this->booleanFlag()->variationForAllUsers($this->_variationForBoolean($variation));
        } else {
            return $this->on(true)->clearRules()->clearUserTargets()->fallthroughVariation($variation);
        }
    }
    

    /**
     * Sets the flag to always return the specified variation value for all users.
     *
     * TODO: Missing from python implementation?
     * TODO: implement this link in php if possible
     * The value may be of any JSON type, as defined by {@link LDValue}. This method 
     * changes the flag to have only a single variation, which is this value, and to return 
     * the same variation regardless of whether targeting is on or off. Any existing targets 
     * or rules are removed.
     * 
     * @param value the desired value to be returned for all users
     * @return FlagBuilder the flag builder
     */
    public function valueForAllUsers(bool|int|string|array|object|null $value) {
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
     * Removes any existing user targets from the flag. This undoes the effect of methods like
     * TODO: implement this link in php if possible
     * {@link #variationForUser(String, boolean)}.
     * 
     * @return FlagBuilder the same builder
     */
    public function clearUserTargets() {
      $this->_targets = [];
      return $this;
    }


    /**
     * Removes any existing rules from the flag. This undoes the effect of methods like
     * TODO: implement this link in php if possible
     * {@link #ifMatch(UserAttribute, LDValue...)}.
     * 
     * @return FlagBuilder the same builder
     */
    public function clearRules()
    {
      $this->_rules = [];
      return $this;
    }
    

    /**    
     * Converts a boolean to the corresponding variation index.
     *
     * @param boolean $variation the boolean variation value
     * @return int the variation index for the given boolean
     */
    private function _variationForBoolean($variation)
    {
        if ($variation) {
            return TRUE_VARIATION_INDEX;
        } else {
            return FALSE_VARIATION_INDEX;
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
     * Removes any existing targets from the flag.
     * This undoes the effect of methods like
     *      `$ldclient->integrations->test_data->FlagBuilder->variationForUser()`
     *
     * @return FlagBuilder the same flag builder
     */
    public function clearTargets()
    {
        $this->_targets = []; 
        return $this;
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
     * @param $userKey string a user key
     * @param $variation int|bool the desired variation to be returned for this user when targeting is on:
     *   0 for the first, 1 for the second, etc.
     * @return FlagBuilder the flag builder
     */
    public function variationForUser(string $userKey, int|bool $variation)
    {
        if (is_bool($variation)) {
            return $this->booleanFlag()
                        ->variationForUser($userKey, $this->_variationForBoolean($variation));
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
                        // $userKeyIdx can be 0,1,2,3 etc
                        // or false if not found. Needs a strict
                        // check to ensure it doesn't eval to true
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
     * Creates a dictionary representation of the flag
     *
     * @param int $version: the version number of the rule
     * @return: the array representation of the flag
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

        return $baseFlagObject;
    }

}
