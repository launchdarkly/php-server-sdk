<?php

namespace LaunchDarkly;

/**
 * Describes the reason that a flag evaluation produced a particular value.
 *
 * This is part of the {@link \LaunchDarkly\EvaluationDetail} object returned by {@link \LaunchDarkly\LDClient::variationDetail()}.
 */
class EvaluationReason implements \JsonSerializable
{
    /**
     * A possible value for getKind(): indicates that the flag was off and therefore returned
     * its configured off value.
     * @var string
     */
    const OFF = 'OFF';
    /**
     * A possible value for getKind(): indicates that the flag was on but the user did not
     * match any targets or rules.
     * @var string
     */
    const FALLTHROUGH = 'FALLTHROUGH';
    /**
     * A possible value for getKind(): indicates that the user key was specifically targeted
     * for this flag.
     * @var string
     */
    const TARGET_MATCH = 'TARGET_MATCH';
    /**
     * A possible value for getKind(): indicates that the user matched one of the flag's rules.
     * @var string
     */
    const RULE_MATCH = 'RULE_MATCH';
    /**
     * A possible value for getKind(): indicates that the flag was considered off because it
     * had at least one prerequisite flag that either was off or did not return the desired variation.
     * @var string
     */
    const PREREQUISITE_FAILED = 'PREREQUISITE_FAILED';
    /**
     * A possible value for getKind(): indicates that the flag could not be evaluated, e.g.
     * because it does not exist or due to an unexpected error.
     * @var string
     */
    const ERROR = 'ERROR';

    /**
     * A possible value for getErrorKind(): indicates that the caller tried to evaluate a flag
     * before the client had successfully initialized.
     * @var string
     */
    const CLIENT_NOT_READY_ERROR = 'CLIENT_NOT_READY';

    /**
     * A possible value for getErrorKind(): indicates that the caller provided a flag key that
     * did not match any known flag.
     * @var string
     */
    const FLAG_NOT_FOUND_ERROR = 'FLAG_NOT_FOUND';

    /**
     * A possible value for getErrorKind(): indicates that there was an internal inconsistency
     * in the flag data, e.g. a rule specified a nonexistent variation. An error message will
     * always be logged in this case.
     * @var string
     */
    const MALFORMED_FLAG_ERROR = 'MALFORMED_FLAG';

    /**
     * A possible value for getErrorKind(): indicates that the caller passed null for the user
     * parameter, or the user lacked a key.
     * @var string
     */
    const USER_NOT_SPECIFIED_ERROR = 'USER_NOT_SPECIFIED';

    /**
     * A possible value for getErrorKind(): indicates that an unexpected exception stopped flag
     * evaluation.
     * @var string
     */
    const EXCEPTION_ERROR = 'EXCEPTION';

    private $_kind;
    private $_errorKind;
    private $_ruleIndex;
    private $_ruleId;
    private $_prerequisiteKey;

    /**
     * Creates a new instance of the OFF reason.
     * @return EvaluationReason
     */
    public static function off()
    {
        return new EvaluationReason(self::OFF);
    }

    /**
     * Creates a new instance of the FALLTHROUGH reason.
     * @return EvaluationReason
     */
    public static function fallthrough()
    {
        return new EvaluationReason(self::FALLTHROUGH);
    }

    /**
     * Creates a new instance of the TARGET_MATCH reason.
     * @return EvaluationReason
     */
    public static function targetMatch()
    {
        return new EvaluationReason(self::TARGET_MATCH);
    }

    /**
     * Creates a new instance of the RULE_MATCH reason.
     * @return EvaluationReason
     */
    public static function ruleMatch($ruleIndex, $ruleId)
    {
        return new EvaluationReason(self::RULE_MATCH, null, $ruleIndex, $ruleId);
    }

    /**
     * Creates a new instance of the PREREQUISITE_FAILED reason.
     * @return EvaluationReason
     */
    public static function prerequisiteFailed($prerequisiteKey)
    {
        return new EvaluationReason(self::PREREQUISITE_FAILED, null, null, null, $prerequisiteKey);
    }

    /**
     * Creates a new instance of the ERROR reason.
     * @return EvaluationReason
     */
    public static function error($errorKind)
    {
        return new EvaluationReason(self::ERROR, $errorKind);
    }

    private function __construct($kind, $errorKind = null, $ruleIndex = null, $ruleId = null, $prerequisiteKey = null)
    {
        $this->_kind = $kind;
        $this->_errorKind = $errorKind;
        $this->_ruleIndex = $ruleIndex;
        $this->_ruleId = $ruleId;
        $this->_prerequisiteKey = $prerequisiteKey;
    }

    /**
     * Returns a constant indicating the general category of the reason, such as OFF.
     * @return string
     */
    public function getKind()
    {
        return $this->_kind;
    }

    /**
     * Returns a constant indicating the nature of the error, if getKind() is OFF. Otherwise
     * returns null.
     * @return string|null
     */
    public function getErrorKind()
    {
        return $this->_errorKind;
    }

    /**
     * Returns the positional index of the rule that was matched (0 for the first), if getKind()
     * is RULE_MATCH. Otherwise returns null.
     * @return int|null
     */
    public function getRuleIndex()
    {
        return $this->_ruleIndex;
    }

    /**
     * Returns the unique identifier of the rule that was matched, if getKind() is RULE_MATCH.
     * Otherwise returns null.
     * @return string|null
     */
    public function getRuleId()
    {
        return $this->_ruleId;
    }

    /**
     * Returns the key of the prerequisite feature flag that failed, if getKind() is
     * PREREQUISITE_FAILED. Otherwise returns null.
     * @return string|null
     */
    public function getPrerequisiteKey()
    {
        return $this->_prerequisiteKey;
    }

    /**
     * Returns a simple string representation of this object.
     */
    public function __toString()
    {
        switch ($this->_kind) {
            case self::RULE_MATCH:
                return $this->_kind . '(' . $this->_ruleIndex . ',' . $this->_ruleId . ')';
            case self::PREREQUISITE_FAILED:
                return $this->_kind . '(' . $this->_prerequisiteKey . ')';
            case self::ERROR:
                return $this->_kind . '(' . $this->_errorKind . ')';
            default:
                return $this->_kind;
        }
    }

    /**
     * Returns a JSON representation of this object. This method is used automatically
     * if you call json_encode().
     */
    public function jsonSerialize()
    {
        $ret = array('kind' => $this->_kind);
        if ($this->_errorKind !== null) {
            $ret['errorKind'] = $this->_errorKind;
        }
        if ($this->_ruleIndex !== null) {
            $ret['ruleIndex'] = $this->_ruleIndex;
        }
        if ($this->_ruleId !== null) {
            $ret['ruleId'] = $this->_ruleId;
        }
        if ($this->_prerequisiteKey !== null) {
            $ret['prerequisiteKey'] = $this->_prerequisiteKey;
        }
        return $ret;
    }
}
