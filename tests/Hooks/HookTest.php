<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Hooks;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Hooks\EvaluationSeriesContext;
use LaunchDarkly\Hooks\Hook;
use LaunchDarkly\Hooks\Metadata;
use LaunchDarkly\Hooks\TrackSeriesContext;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;

class HookTest extends TestCase
{
    public function testDefaultBeforeEvaluationReturnsInputData(): void
    {
        $hook = new class extends Hook {
            public function getMetadata(): Metadata
            {
                return new Metadata('test');
            }
        };

        $ctx = new EvaluationSeriesContext('flag', LDContext::create('u'), false, 'variation');
        $data = ['foo' => 'bar'];
        $this->assertSame($data, $hook->beforeEvaluation($ctx, $data));
    }

    public function testDefaultAfterEvaluationReturnsInputData(): void
    {
        $hook = new class extends Hook {
            public function getMetadata(): Metadata
            {
                return new Metadata('test');
            }
        };

        $ctx = new EvaluationSeriesContext('flag', LDContext::create('u'), false, 'variation');
        $detail = new EvaluationDetail(true, 0, EvaluationReason::off());
        $data = ['foo' => 'bar'];
        $this->assertSame($data, $hook->afterEvaluation($ctx, $data, $detail));
    }

    public function testDefaultAfterTrackIsNoOp(): void
    {
        $hook = new class extends Hook {
            public function getMetadata(): Metadata
            {
                return new Metadata('test');
            }
        };

        $ctx = new TrackSeriesContext(LDContext::create('u'), 'event', null, null);
        // Simply invoking should not throw or have observable effects.
        $hook->afterTrack($ctx);
        $this->assertTrue(true);
    }
}
