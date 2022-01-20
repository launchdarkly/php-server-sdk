<?php

namespace LaunchDarkly\Integrations;

class TestData {

    public function flag($key)
    {

        return new FlagBuilder($key);

    }

}

class FlagBuilder {

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
    public function on($on) {

        $this->_on = $on;
        return $this;

    }

}
