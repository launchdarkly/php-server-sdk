<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use GuzzleHttp\Client;
use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\Hooks\EvaluationSeriesContext;
use LaunchDarkly\Hooks\Hook;
use LaunchDarkly\Hooks\Metadata;
use LaunchDarkly\Hooks\TrackSeriesContext;

/**
 * Contract-test hook. Posts each stage invocation to a callback URL; optionally throws
 * from configured stages.
 * contract test services.
 */
class PostingHook extends Hook
{
    public function __construct(
        private readonly string $name,
        private readonly string $callbackUri,
        private readonly array $data,
        private readonly array $errors,
    ) {
    }

    public function getMetadata(): Metadata
    {
        return new Metadata($this->name);
    }

    public function beforeEvaluation(EvaluationSeriesContext $seriesContext, array $data): array
    {
        return $this->post('beforeEvaluation', $seriesContext, $data, null);
    }

    public function afterEvaluation(
        EvaluationSeriesContext $seriesContext,
        array $data,
        EvaluationDetail $detail,
    ): array {
        return $this->post('afterEvaluation', $seriesContext, $data, $detail);
    }

    public function afterTrack(TrackSeriesContext $seriesContext): void
    {
        $stage = 'afterTrack';
        if (isset($this->errors[$stage])) {
            throw new Exception($this->errors[$stage]);
        }
        $payload = [
            'stage' => $stage,
            'trackSeriesContext' => [
                'key' => $seriesContext->key,
                'context' => json_decode(json_encode($seriesContext->context), true),
                'data' => $seriesContext->data,
                'metricValue' => $seriesContext->metricValue,
            ],
        ];
        (new Client())->post($this->callbackUri, ['json' => $payload]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function post(
        string $stage,
        EvaluationSeriesContext $seriesContext,
        array $data,
        ?EvaluationDetail $detail,
    ): array {
        if (isset($this->errors[$stage])) {
            throw new Exception($this->errors[$stage]);
        }

        $payload = [
            'evaluationSeriesContext' => [
                'flagKey' => $seriesContext->flagKey,
                'context' => json_decode(json_encode($seriesContext->context), true),
                'defaultValue' => $seriesContext->defaultValue,
                'method' => $seriesContext->method,
            ],
            'evaluationSeriesData' => (object) $data,
            'stage' => $stage,
        ];

        if ($detail !== null) {
            $payload['evaluationDetail'] = [
                'value' => $detail->getValue(),
                'variationIndex' => $detail->getVariationIndex(),
                'reason' => $detail->getReason(),
            ];
        }

        (new Client())->post($this->callbackUri, ['json' => $payload]);

        return array_merge($data, $this->data[$stage] ?? []);
    }
}
