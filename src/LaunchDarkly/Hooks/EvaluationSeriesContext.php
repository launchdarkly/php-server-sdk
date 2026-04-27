<?php

declare(strict_types=1);

namespace LaunchDarkly\Hooks;

use LaunchDarkly\LDContext;

/**
 * Contextual information provided to stages of the evaluation series.
 *
 * One instance is provided to each stage of the evaluation series. The instance
 * passed to `beforeEvaluation` and the one passed to `afterEvaluation` may differ
 * in their `environmentId` value: the env ID is unknown until the SDK has fetched
 * flag data from LaunchDarkly at least once, so the first variation call's
 * `beforeEvaluation` typically sees `null` while its `afterEvaluation` sees the
 * captured value. See {@see \LaunchDarkly\Hooks\EvaluationSeriesContext::$environmentId}.
 */
final class EvaluationSeriesContext
{
    /**
     * @param string $flagKey The key of the flag being evaluated.
     * @param LDContext $context The evaluation context.
     * @param mixed $defaultValue The default value the caller passed to the variation method.
     * @param string $method The variation method being executed (e.g. `variation`, `variationDetail`).
     * @param ?string $environmentId The LaunchDarkly environment ID associated with the SDK key,
     *   if known. Populated from the `X-Ld-Envid` response header when the SDK fetches flags
     *   directly from LaunchDarkly. Always `null` when using a persistent-store feature requester
     *   (e.g. Redis, Consul, DynamoDB), and `null` for the very first variation call's
     *   `beforeEvaluation` stage in a PHP process (the value is captured during evaluation).
     */
    public function __construct(
        public readonly string $flagKey,
        public readonly LDContext $context,
        public readonly mixed $defaultValue,
        public readonly string $method,
        public readonly ?string $environmentId = null,
    ) {
    }
}
