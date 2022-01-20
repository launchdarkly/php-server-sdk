<?php

namespace LaunchDarkly\Integrations;

class TestData {

    public function flag(string $key): FlagBuilder
    {

        return new FlagBuilder($key);

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
    protected $_off_variation;
    /** @var int|null */
    protected $_fallthrough_variation;
    /** @var array */
    protected $_targets;
    /** @var array */
    protected $_rules;

    public function __construct(string $key)
    {

        $this->_key = $key;
        $this->_on = true;
        $this->_variations = [];
        $this->_off_variation = null;
        $this->_fallthrough_variation = null;
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
        $to->_off_variation = $this->_off_variation;
        $to->_fallthrough_variation = $this->_fallthrough_variation;
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
     *  Changes the allowable variation values for the flag. 
     *  
     *  The value may be of any valid JSON type. For instance, a boolean flag
     *  normally has True, False; a string-valued flag might have
     *  'red', 'green'; etc.
     *
     *  Example: A single variation
     *
     *      $td->flag('new-flag')->variations(True)
     *
     *  Example: Multiple variations
     *
     *      $td->flag('new-flag')->variations('red', 'green', 'blue')
     *
     *  @param array $variations the the desired variations
     *  @return FlagBuilder the flag builder object
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

    public function build(int $version): array
    {
        $base_flag_object = [
            'key'        => $this->_key,
            'version'    => $version,
            'on'         => $this->_on,
            'variations' => $this->_variations
        ];
        return $base_flag_object;
    }

}
