<?php

declare(strict_types=1);

namespace LaunchDarkly;

/**
 * A mutable object that uses the builder pattern to specify properties for a multi-context.
 *
 * Use this builder if you need to construct an {@see \LaunchDarkly\LDContext} that contains
 * multiple contexts, each for a different context kind. To define a regular context for a
 * single kind, use {@see \LaunchDarkly\LDContext::create()} or
 * {@see \LaunchDarkly\LDContext::builder()}.
 *
 * Obtain an instance of LDContextMultiBuilder by calling {@see \LaunchDarkly\LDContext::multiBuilder()};
 * then, call {@see \LaunchDarkly\LDContextMultiBuilder::add()} to specify the individual
 * context for each kind. The method returns a reference to the same builder, so calls can be
 * chained:
 * ```php
 *     $context = LDContext::multiBuilder()
 *       ->add(LDContext::create('my-user-key'))
 *       ->add(LDContext::create('my-org-key', 'organization'))
 *       ->build();
 * ```
 *
 * @see \LaunchDarkly\LDContext
 */
class LDContextMultiBuilder
{
    private array $_contexts = [];

    /**
     * Creates an LDContext from the current builder properties.
     *
     * The LDContext is immutable and will not be affected by any subsequent actions on the
     * builder.
     *
     * It is possible for an LDContextMultiBuilder to represent an invalid state. Instead of
     * throwing an exception, the LDContextMultiBuilder always returns an LDContext, and you
     * can check {@see \LaunchDarkly\LDContext::isValid()} or
     * {@see \LaunchDarkly\LDContext::getError()} to see if it has an error. See
     * {@see \LaunchDarkly\LDContext::isValid()} for more information about invalid context
     * conditions. If you pass an invalid context to an SDK method, the SDK will
     * detect this and will log a description of the error.
     *
     * If only one context was added to the builder, this method returns that context rather
     * than a multi-context.
     *
     * @return LDContext a new LDContext
     */
    public function build(): LDContext
    {
        if (count($this->_contexts) === 1) {
            return $this->_contexts[0]; // multi-context with only one context is the same as just that context
        }
        // LDContext constructor will handle validation
        return new LDContext(LDContext::MULTI_KIND, '', null, false, null, null, $this->_contexts, null);
    }

    /**
     * Adds an individual LDContext for a specific kind to the builer.
     *
     * It is invalid to add more than one LDContext for the same kind, or to add an LDContext
     * that is itself invalid. This error is detected when you call
     * {@see \LaunchDarkly\LDContextMultiBuilder::build()}.
     *
     * If the nested context is a multi-context, this is exactly equivalent to adding each of the
     * individual contexts from it separately. For instance, in the following example, `$multi1` and
     * `$multi2` end up being exactly the same:
     * ```php
     *     $c1 = LDContext::create('key1', 'kind1');
     *     $c2 = LDContext::create('key2', 'kind2');
     *     $c3 = LDContext::create('key3', 'kind3');'
     *
     *     $multi1 = LDContext::multiBuilder()->add($c1)->add($c2)->add($c3).build();
     *
     *     $c1plus2 = LDContext::multiBuilder()->add($c1)->add($c2).build();
     *     $multi2 = LDContext::multiBuilder()->add($c1plus2)->add($c3)->build();
     * ```
     *
     * @param LDContext $context the context to add
     * @return LDContextMultiBuilder the builder
     */
    public function add(LDContext $context): LDContextMultiBuilder
    {
        if ($context->isMultiple()) {
            for ($i = 0; $i < $context->getIndividualContextCount(); $i++) {
                $c = $context->getIndividualContext($i);
                if ($c) {
                    $this->add($c);
                }
            }
            return $this;
        }
        $this->_contexts[] = $context;
        return $this;
    }
}
