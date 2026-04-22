<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Hooks;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\Hooks\EvaluationSeriesContext;
use LaunchDarkly\Hooks\Hook;
use LaunchDarkly\Hooks\TrackSeriesContext;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @ignore
 * @internal
 *
 * Runs hook stages with the required ordering and error isolation behavior.
 */
final class HookRunner
{
    /** @var list<Hook> */
    private array $_hooks;

    /**
     * @param list<Hook> $hooks
     */
    public function __construct(
        private readonly LoggerInterface $_logger,
        array $hooks = [],
    ) {
        $this->_hooks = $hooks;
    }

    public function addHook(Hook $hook): void
    {
        $this->_hooks[] = $hook;
    }

    public function hasHooks(): bool
    {
        return count($this->_hooks) > 0;
    }

    /**
     * Executes the beforeEvaluation stage of every registered hook in registration order.
     *
     * @return list<array<string, mixed>> The data returned by each hook, in the same order as
     *     the registered hooks. On error, the slot for that hook contains an empty array.
     */
    public function beforeEvaluation(EvaluationSeriesContext $seriesContext): array
    {
        $result = [];
        foreach ($this->_hooks as $hook) {
            $result[] = $this->safeInvoke(
                'beforeEvaluation',
                $seriesContext->flagKey,
                $hook,
                fn () => $hook->beforeEvaluation($seriesContext, []),
                [],
            );
        }
        return $result;
    }

    /**
     * Executes the afterEvaluation stage of every registered hook in reverse registration order.
     *
     * @param list<array<string, mixed>> $beforeData The per-hook data returned from beforeEvaluation.
     */
    public function afterEvaluation(
        EvaluationSeriesContext $seriesContext,
        array $beforeData,
        EvaluationDetail $detail,
    ): void {
        for ($i = count($this->_hooks) - 1; $i >= 0; $i--) {
            $hook = $this->_hooks[$i];
            $data = $beforeData[$i] ?? [];
            $this->safeInvoke(
                'afterEvaluation',
                $seriesContext->flagKey,
                $hook,
                fn () => $hook->afterEvaluation($seriesContext, $data, $detail),
                $data,
            );
        }
    }

    /**
     * Executes the afterTrack handler of every registered hook in registration order.
     */
    public function afterTrack(TrackSeriesContext $seriesContext): void
    {
        foreach ($this->_hooks as $hook) {
            try {
                $hook->afterTrack($seriesContext);
            } catch (Throwable $e) {
                $name = $this->hookName($hook);
                $this->_logger->error(
                    "During tracking of event \"{$seriesContext->key}\", stage \"afterTrack\" of hook \"{$name}\" reported error: {$e->getMessage()}"
                );
            }
        }
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @param T $fallback
     * @return T
     */
    private function safeInvoke(string $stage, string $flagKey, Hook $hook, callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            $name = $this->hookName($hook);
            $this->_logger->error(
                "During evaluation of flag \"{$flagKey}\", stage \"{$stage}\" of hook \"{$name}\" reported error: {$e->getMessage()}"
            );
            return $fallback;
        }
    }

    private function hookName(Hook $hook): string
    {
        try {
            return $hook->getMetadata()->name;
        } catch (Throwable) {
            return 'unknown hook';
        }
    }
}
