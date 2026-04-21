<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Hooks;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Hooks\EvaluationSeriesContext;
use LaunchDarkly\Hooks\TrackSeriesContext;
use LaunchDarkly\Impl\Hooks\HookRunner;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class HookRunnerTest extends TestCase
{
    private function ctx(): EvaluationSeriesContext
    {
        return new EvaluationSeriesContext('flag-key', LDContext::create('u'), 'default', 'variation');
    }

    private function detail(): EvaluationDetail
    {
        return new EvaluationDetail('value', 0, EvaluationReason::fallthrough());
    }

    private function nullLogger(): LoggerInterface
    {
        return new \Psr\Log\NullLogger();
    }

    public function testBeforeEvaluationRunsInRegistrationOrder(): void
    {
        $shared = [];
        $a = new RecordingHook('A', $shared);
        $b = new RecordingHook('B', $shared);
        $runner = new HookRunner($this->nullLogger(), [$a, $b]);

        $runner->beforeEvaluation($this->ctx());

        $this->assertSame(['A', 'B'], array_column($shared, 'hook'));
    }

    public function testAfterEvaluationRunsInReverseRegistrationOrder(): void
    {
        $shared = [];
        $a = new RecordingHook('A', $shared);
        $b = new RecordingHook('B', $shared);
        $runner = new HookRunner($this->nullLogger(), [$a, $b]);

        $before = $runner->beforeEvaluation($this->ctx());
        $runner->afterEvaluation($this->ctx(), $before, $this->detail());

        // beforeEvaluation: A, B; afterEvaluation: B, A.
        $this->assertSame(
            ['A:beforeEvaluation', 'B:beforeEvaluation', 'B:afterEvaluation', 'A:afterEvaluation'],
            array_map(fn ($e) => $e['hook'] . ':' . $e['stage'], $shared),
        );
    }

    public function testSeriesDataIsPropagatedPerHook(): void
    {
        $a = new RecordingHook('A', returnData: ['beforeEvaluation' => ['from-a' => 1]]);
        $b = new RecordingHook('B', returnData: ['beforeEvaluation' => ['from-b' => 2]]);
        $runner = new HookRunner($this->nullLogger(), [$a, $b]);

        $before = $runner->beforeEvaluation($this->ctx());
        $runner->afterEvaluation($this->ctx(), $before, $this->detail());

        // Each hook sees only its own beforeEvaluation data.
        $this->assertSame(['from-a' => 1], $a->calls[1]['data']);
        $this->assertSame(['from-b' => 2], $b->calls[1]['data']);
    }

    public function testBeforeEvaluationErrorYieldsEmptyDataForThatHook(): void
    {
        $a = new RecordingHook('A', throwFrom: ['beforeEvaluation' => new RuntimeException('boom')]);
        $b = new RecordingHook('B', returnData: ['beforeEvaluation' => ['from-b' => 2]]);
        $runner = new HookRunner($this->nullLogger(), [$a, $b]);

        $before = $runner->beforeEvaluation($this->ctx());
        $runner->afterEvaluation($this->ctx(), $before, $this->detail());

        // Hook A's afterEvaluation receives [] because its beforeEvaluation failed.
        $this->assertSame([], $a->calls[0]['data']);
        // Hook B is unaffected.
        $this->assertSame(['from-b' => 2], $b->calls[1]['data']);
    }

    public function testAfterEvaluationErrorIsLoggedAndIsolated(): void
    {
        $a = new RecordingHook('A', throwFrom: ['afterEvaluation' => new RuntimeException('boom')]);
        $b = new RecordingHook('B');
        $runner = new HookRunner($this->nullLogger(), [$a, $b]);

        $before = $runner->beforeEvaluation($this->ctx());
        $runner->afterEvaluation($this->ctx(), $before, $this->detail());

        // Both hooks' afterEvaluation still ran, despite A throwing.
        $this->assertCount(2, $a->calls);
        $this->assertCount(2, $b->calls);
    }

    public function testExceptionLoggedWithRequiredFormat(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->equalTo('During evaluation of flag "flag-key", stage "beforeEvaluation" of hook "A" reported error: boom'));

        $a = new RecordingHook('A', throwFrom: ['beforeEvaluation' => new RuntimeException('boom')]);
        $runner = new HookRunner($logger, [$a]);
        $runner->beforeEvaluation($this->ctx());
    }

    public function testAfterTrackRunsInRegistrationOrder(): void
    {
        $shared = [];
        $a = new RecordingHook('A', $shared);
        $b = new RecordingHook('B', $shared);
        $runner = new HookRunner($this->nullLogger(), [$a, $b]);

        $runner->afterTrack(new TrackSeriesContext(LDContext::create('u'), 'event', null, null));

        $this->assertSame(['A', 'B'], array_column($shared, 'hook'));
    }

    public function testAfterTrackExceptionIsLoggedAndIsolated(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('stage "afterTrack" of hook "A" reported error: boom'));

        $a = new RecordingHook('A', throwFrom: ['afterTrack' => new RuntimeException('boom')]);
        $b = new RecordingHook('B');
        $runner = new HookRunner($logger, [$a, $b]);

        $runner->afterTrack(new TrackSeriesContext(LDContext::create('u'), 'event', null, null));

        // B still runs after A throws.
        $this->assertCount(1, $b->calls);
    }

    public function testAddHookAppendsToEndOfOrder(): void
    {
        $shared = [];
        $a = new RecordingHook('A', $shared);
        $runner = new HookRunner($this->nullLogger(), [$a]);

        $b = new RecordingHook('B', $shared);
        $runner->addHook($b);

        $runner->beforeEvaluation($this->ctx());
        $this->assertSame(['A', 'B'], array_column($shared, 'hook'));
    }

    public function testHasHooksReflectsRegistrationState(): void
    {
        $runner = new HookRunner($this->nullLogger(), []);
        $this->assertFalse($runner->hasHooks());

        $runner->addHook(new RecordingHook('A'));
        $this->assertTrue($runner->hasHooks());
    }
}
