<?php

namespace LaunchDarkly\Integrations\TestData;

/**
 * A builder for feature flag rules to be used with {@see \LaunchDarkly\Integrations\TestData\FlagBuilder}.
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
            "values" => $values,
            "negate" => false,
        ];
        $this->_clauses[] = $newClause;
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
        $this->_clauses[] = $newClause;
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
            "id" => "rule{$id}",
            "variation" => $this->_variation,
            "clauses" => $this->_clauses
        ];
    }
}
