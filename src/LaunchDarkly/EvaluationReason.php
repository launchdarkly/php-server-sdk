<?php

declare(strict_types=1);

namespace LaunchDarkly;

/**
 * Describes the reason that a flag evaluation produced a particular value.
 *
 * This is part of the {@see \LaunchDarkly\EvaluationDetail} object returned by {@see \LaunchDarkly\LDClient::variationDetail()}.
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

    /**
     * A possible value for getErrorKind(): indicates the value of the
     * evaluation did not match the PHP type expected.
     */

    const WRONG_TYPE_ERROR = 'WRONG_TYPE';

    private string $_kind;
    private ?string $_errorKind;
    private ?int $_ruleIndex;
    private ?string $_ruleId;
    private ?string $_prerequisiteKey;
    private bool $_inExperiment;

    /**
     * Creates a new instance of the OFF reason.
     * @return EvaluationReason
     */
    public static function off(): EvaluationReason
    {
        return new EvaluationReason(self::OFF);
    }

    /**
     * Creates a new instance of the FALLTHROUGH reason.
     * @return EvaluationReason
     */
    public static function fallthrough(bool $inExperiment = false): EvaluationReason
    {
        return new EvaluationReason(self::FALLTHROUGH, null, null, null, null, $inExperiment);
    }

    /**
     * Creates a new instance of the TARGET_MATCH reason.
     * @return EvaluationReason
     */
    public static function targetMatch(): EvaluationReason
    {
        return new EvaluationReason(self::TARGET_MATCH);
    }

    /**
     * Creates a new instance of the RULE_MATCH reason.
     *
     * @return EvaluationReason
     *
     * @param null|int $ruleIndex
     * @param null|string $ruleId
     */
    public static function ruleMatch(?int $ruleIndex, ?string $ruleId, bool $inExperiment = false): EvaluationReason
    {
        return new EvaluationReason(self::RULE_MATCH, null, $ruleIndex, $ruleId, null, $inExperiment);
    }

    /**
     * Creates a new instance of the PREREQUISITE_FAILED reason.
     * @return EvaluationReason
     */
    public static function prerequisiteFailed(string $prerequisiteKey): EvaluationReason
    {
        return new EvaluationReason(self::PREREQUISITE_FAILED, null, null, null, $prerequisiteKey);
    }

    /**
     * Creates a new instance of the ERROR reason.
     *
     * @return EvaluationReason
     *
     * @param string $errorKind
     */
    public static function error(string $errorKind): EvaluationReason
    {
        return new EvaluationReason(self::ERROR, $errorKind);
    }

    private function __construct(
        string $kind,
        ?string $errorKind = null,
        ?int $ruleIndex = null,
        ?string $ruleId = null,
        ?string $prerequisiteKey = null,
        bool $inExperiment = false
    ) {
        $this->_kind = $kind;
        $this->_errorKind = $errorKind;
        $this->_ruleIndex = $ruleIndex;
        $this->_ruleId = $ruleId;
        $this->_prerequisiteKey = $prerequisiteKey;
        $this->_inExperiment = $inExperiment;
    }

    /**
     * Returns a constant indicating the general category of the reason, such as OFF.
     * @return string
     */
    public function getKind(): string
    {
        return $this->_kind;
    }

    /**
     * Returns a constant indicating the nature of the error, if getKind() is OFF. Otherwise
     * returns null.
     * @return string|null
     */
    public function getErrorKind(): ?string
    {
        return $this->_errorKind;
    }

    /**
     * Returns the positional index of the rule that was matched (0 for the first), if getKind()
     * is RULE_MATCH. Otherwise returns null.
     * @return int|null
     */
    public function getRuleIndex(): ?int
    {
        return $this->_ruleIndex;
    }

    /**
     * Returns the unique identifier of the rule that was matched, if getKind() is RULE_MATCH.
     * Otherwise returns null.
     * @return string|null
     */
    public function getRuleId(): ?string
    {
        return $this->_ruleId;
    }

    /**
     * Returns the key of the prerequisite feature flag that failed, if getKind() is
     * PREREQUISITE_FAILED. Otherwise returns null.
     * @return string|null
     */
    public function getPrerequisiteKey(): ?string
    {
        return $this->_prerequisiteKey;
    }

    /**
     * Returns true if the evaluation resulted in an experiment rollout *and* served
     * one of the variations in the experiment.  Otherwise it returns false.
     * @return bool
     */
    public function isInExperiment(): bool
    {
        return $this->_inExperiment;
    }

    /**
     * Returns a simple string representation of this object.
     */
    public function __toString(): string
    {
        switch ($this->_kind) {
            case self::RULE_MATCH:
                return $this->_kind . '(' . ($this->_ruleIndex ?: 0) . ',' . ($this->_ruleId ?: '') . ')';
            case self::PREREQUISITE_FAILED:
                return $this->_kind . '(' . ($this->_prerequisiteKey ?: '') . ')';
            case self::ERROR:
                return $this->_kind . '(' . ($this->_errorKind ?: '') . ')';
            default:
                return $this->_kind;
        }
    }

    /**
     * Returns a JSON representation of this object. This method is used automatically
     * if you call json_encode().
     */
    public function jsonSerialize(): array
    {
        $ret = ['kind' => $this->_kind];
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
        if ($this->_inExperiment) {
            $ret['inExperiment'] = $this->_inExperiment;
        }
        return $ret;
    }
}
