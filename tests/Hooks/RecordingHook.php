<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Hooks;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\Hooks\EvaluationSeriesContext;
use LaunchDarkly\Hooks\Hook;
use LaunchDarkly\Hooks\Metadata;
use LaunchDarkly\Hooks\TrackSeriesContext;

/**
 * Hook that records every stage invocation to a shared log list. Optionally throws
 * from configured stages and/or returns configured series data.
 */
class RecordingHook extends Hook
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    /**
     * @param CallLog|null $sharedLog When provided, all hooks sharing the same CallLog append to it
     *   in invocation order. Allows cross-hook ordering assertions.
     * @param array<string, \Throwable> $throwFrom Maps stage name ('beforeEvaluation'|'afterEvaluation'|'afterTrack')
     *   to the exception that should be thrown when that stage runs.
     * @param array<string, array<string, mixed>> $returnData Additional data to merge into the return value of
     *   the given stage.
     */
    public function __construct(
        private readonly string $name,
        private readonly ?CallLog $sharedLog = null,
        private readonly array $throwFrom = [],
        private readonly array $returnData = [],
    ) {
    }

    public function getMetadata(): Metadata
    {
        return new Metadata($this->name);
    }

    public function beforeEvaluation(EvaluationSeriesContext $seriesContext, array $data): array
    {
        $this->record('beforeEvaluation', ['ctx' => $seriesContext, 'data' => $data]);
        if (isset($this->throwFrom['beforeEvaluation'])) {
            throw $this->throwFrom['beforeEvaluation'];
        }
        return array_merge($data, $this->returnData['beforeEvaluation'] ?? []);
    }

    public function afterEvaluation(
        EvaluationSeriesContext $seriesContext,
        array $data,
        EvaluationDetail $detail,
    ): array {
        $this->record('afterEvaluation', ['ctx' => $seriesContext, 'data' => $data, 'detail' => $detail]);
        if (isset($this->throwFrom['afterEvaluation'])) {
            throw $this->throwFrom['afterEvaluation'];
        }
        return array_merge($data, $this->returnData['afterEvaluation'] ?? []);
    }

    public function afterTrack(TrackSeriesContext $seriesContext): void
    {
        $this->record('afterTrack', ['ctx' => $seriesContext]);
        if (isset($this->throwFrom['afterTrack'])) {
            throw $this->throwFrom['afterTrack'];
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function record(string $stage, array $payload): void
    {
        $entry = ['hook' => $this->name, 'stage' => $stage] + $payload;
        $this->calls[] = $entry;
        $this->sharedLog?->append($entry);
    }
}
