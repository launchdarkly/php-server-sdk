<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations\TestData;

use LaunchDarkly\LDContext;

/**
 * A builder for feature flag rules to be used with FlagBuilder.
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
 *
 * @see \LaunchDarkly\Integrations\TestData\FlagBuilder
 */
class FlagRuleBuilder
{
    protected FlagBuilder $_flagBuilder;
    protected array $_clauses;
    protected ?int $_variation;

    public function __construct(FlagBuilder $flagBuilder)
    {
        $this->_flagBuilder = $flagBuilder;
        $this->_clauses = [];
        $this->_variation = null;
    }

    /**
     * Adds another clause, using the "is one of" operator.
     *
     * This is a shortcut for calling {@see \LaunchDarkly\Integrations\TestData\FlagRuleBuilder::andMatchContext()}
     * with `LDContext::DEFAULT_KIND` as the context kind.
     *
     * For example, this creates a rule that returns `true` if the name is "Patsy" and the
     * country is "gb":
     *
     *     $testData->flag("flag")
     *              ->ifMatch("name", "Patsy")
     *              ->andMatch("country", "gb")
     *              ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder the rule builder
     */
    public function andMatch(string $attribute, mixed ...$values)
    {
        return $this->andMatchContext(LDContext::DEFAULT_KIND, $attribute, ...$values);
    }

    /**
     * Adds another clause, using the "is one of" operator. This matching expression only
     * applies to contexts of a specific kind.
     *
     * For example, this creates a rule that returns `true` if the name attribute for the
     * "company" context is "Ella", and the country attribute for the "company" context is "gb":
     *
     *     $testData->flag("flag")
     *              ->ifMatchContext("company", "name", "Ella")
     *              ->andMatchContext("company", "country", "gb")
     *              ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder the rule builder
     */
    public function andMatchContext(string $contextKind, string $attribute, mixed ...$values)
    {
        $newClause = [
            "contextKind" => $contextKind,
            "attribute" => $attribute,
            "op" => 'in',
            "values" => $values,
            "negate" => false,
        ];
        $this->_clauses[] = $newClause;
        return $this;
    }

    /**
     * Adds another clause, using the "is not one of" operator.
     *
     * This is a shortcut for calling {@see \LaunchDarkly\Integrations\TestData\FlagRuleBuilder::andNotMatchContext()}
     * with`LDContext::DEFAULT_KIND` as the context kind.
     *
     * For example, this creates a rule that returns `true` if
     * the name is "Patsy" and the country is not "gb":
     *
     *    $testData->flag("flag")
     *             ->ifMatch("name", "Patsy")
     *             ->andNotMatch("country", "gb")
     *             ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder the rule builder
     */
    public function andNotMatch(string $attribute, mixed ...$values)
    {
        return $this->andNotMatchContext(LDContext::DEFAULT_KIND, $attribute, ...$values);
    }

    /**
     * Adds another clause, using the "is not one of" operator. This matching expression only
     * applies to contexts of a specific kind.
     *
     * For example, this creates a rule that returns `true` if the name attribute for the
     * "company" context is "Ella", and the country attribute for the "company" context is not "gb":
     *
     *    $testData->flag("flag")
     *             ->ifMatchContext("company", "name", "Ella")
     *             ->andNotMatchContext("company", "country", "gb")
     *             ->thenReturn(true);
     *
     * @param string $attribute the user attribute to match against
     * @param mixed[] $values values to compare to
     * @return FlagRuleBuilder the rule builder
     */
    public function andNotMatchContext(string $contextKind, string $attribute, mixed ...$values)
    {
        $newClause = [
            "contextKind" => $contextKind,
            "attribute" => $attribute,
            "op" => 'in',
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
    public function thenReturn(bool|int $variation): FlagBuilder
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
    public function build(int $id): array
    {
        return [
            "id" => "rule{$id}",
            "variation" => $this->_variation,
            "clauses" => $this->_clauses
        ];
    }
}
