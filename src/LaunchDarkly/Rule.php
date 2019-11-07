<?php

namespace LaunchDarkly;

class Rule extends VariationOrRollout
{
    /** @var string */
    private $_id = null;
    /** @var Clause[] */
    private $_clauses = array();
    /** @var boolean */
    private $_trackEvents;

    protected function __construct($variation, $rollout, $id, array $clauses, $trackEvents)
    {
        parent::__construct($variation, $rollout);
        $this->_id = $id;
        $this->_clauses = $clauses;
        $this->_trackEvents = $trackEvents;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new Rule(
                isset($v['variation']) ? $v['variation'] : null,
                isset($v['rollout']) ? call_user_func(Rollout::getDecoder(), $v['rollout']) : null,
                isset($v['id']) ? $v['id'] : null,
                array_map(Clause::getDecoder(), $v['clauses']),
                isset($v['trackEvents']) ? $v['trackEvents'] : false);
        };
    }

    /**
     * @param $user LDUser
     * @return bool
     */
    public function matchesUser($user, $featureRequester)
    {
        foreach ($this->_clauses as $clause) {
            if (!$clause->matchesUser($user, $featureRequester)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }
    
    /**
     * @return Clause[]
     */
    public function getClauses()
    {
        return $this->_clauses;
    }

    /**
     * @return boolean
     */
    public function isTrackEvents()
    {
        return $this->_trackEvents;
    }
}
