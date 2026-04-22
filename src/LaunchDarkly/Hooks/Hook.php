<?php

declare(strict_types=1);

namespace LaunchDarkly\Hooks;

use LaunchDarkly\EvaluationDetail;

/**
 * Base class for extending SDK functionality via hooks.
 *
 * Hook implementations MUST inherit from this class. Default no-op
 * implementations are provided for every stage so the SDK can add new stages
 * without breaking existing implementations.
 *
 * Only `getMetadata` is abstract; subclasses override just the stages they need.
 */
abstract class Hook
{
    /**
     * Get metadata about the hook implementation.
     */
    abstract public function getMetadata(): Metadata;

    /**
     * Stage executed before the flag value has been determined.
     *
     * Called synchronously on the thread performing the variation call.
     *
     * @param array<string, mixed> $data Data returned by the previous stage of this hook.
     *     For beforeEvaluation this will be an empty array.
     * @return array<string, mixed> Data to be passed to the next stage of this hook.
     *     Implementations should return the input data (optionally augmented) unchanged
     *     if they do not need to pass additional information forward.
     */
    public function beforeEvaluation(EvaluationSeriesContext $seriesContext, array $data): array
    {
        return $data;
    }

    /**
     * Stage executed after the flag value has been determined.
     *
     * Called synchronously on the thread performing the variation call.
     *
     * @param array<string, mixed> $data Data returned by the beforeEvaluation stage of this hook.
     * @param EvaluationDetail $detail The result of the evaluation. This value should not be modified.
     * @return array<string, mixed> Data to be passed to the next stage of this hook.
     *     The return is currently unused but future stages may consume it.
     */
    public function afterEvaluation(
        EvaluationSeriesContext $seriesContext,
        array $data,
        EvaluationDetail $detail,
    ): array {
        return $data;
    }

    /**
     * Handler executed after a custom event has been enqueued by a call to `track`.
     *
     * Not invoked if the track call could not enqueue an event (e.g. invalid context).
     */
    public function afterTrack(TrackSeriesContext $seriesContext): void
    {
    }
}
