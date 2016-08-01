<?php
/**
 * Created by IntelliJ IDEA.
 * User: dan
 * Date: 8/1/16
 * Time: 1:29 PM
 */

namespace LaunchDarkly;

class Rule extends VariationOrRollout {
    /** @var Clause[] */
    private $_clauses = array();

    protected function __construct($variation, $rollout, array $clauses) {
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
                error_log("false from rule.matchesuser with attr: " . $clause->getAttribute());
                return false;
            }
        }
        error_log("true from rule.matchesuser");
        return true;
    }

    /**
     * @return Clause[]
     */
    public function getClauses() {
        return $this->_clauses;
    }
}