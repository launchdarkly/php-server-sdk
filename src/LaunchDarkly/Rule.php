<?php

namespace LaunchDarkly;

class Rule extends VariationOrRollout {
    /** @var Clause[] */
    private $_clauses = array();

    public function __construct($variation, $rollout, array $clauses) {
        parent::__construct($variation, $rollout);
        $this->_clauses = $clauses;
    }

    public static function getDecoder() {
        return function ($v) {
            return new Rule(
                isset($v['variation']) ? $v['variation'] : null,
                isset($v['rollout']) ? call_user_func(Rollout::getDecoder(), $v['rollout']) : null,
                array_map(Clause::getDecoder(), $v['clauses']));
        };
    }

    /**
     * @param $user LDUser
     * @return bool
     */
    public function matchesUser($user) {
        foreach ($this->_clauses as $clause) {
            if (!$clause->matchesUser($user)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return Clause[]
     */
    public function getClauses() {
        return $this->_clauses;
    }
}