<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use LaunchDarkly\Impl\Migrations\Executor;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
use LaunchDarkly\LDUser;

/**
 * Migrator is a class for performing a technology migration.
 *
 * This class is not intended to be instanced directly, but instead should be constructed
 * using the {@see MigratorBuilder}.
 */
class Migrator
{
    public function __construct(
        private LDClient $client,
        private ExecutionOrder $executionOrder,
        private MigrationConfig $readConfig,
        private MigrationConfig $writeConfig,
        private bool $trackLatency,
        private bool $trackErrors,
    ) {
    }

    /**
     * Uses the provided flag key and context to execute a migration-backed read operation.
     */
    public function read(
        string $key,
        LDContext|LDUser $context,
        Stage $defaultStage,
        mixed $payload = null
    ): OperationResult {
        $variationResult = $this->client->migrationVariation($key, $context, $defaultStage);
        /** @var Stage */
        $stage = $variationResult['stage'];
        /** @var OpTracker */
        $tracker = $variationResult['tracker'];
        $tracker->operation(Operation::READ);

        $old = new Executor(Origin::OLD, $this->readConfig->old, $tracker, $this->trackLatency, $this->trackErrors, $payload);
        $new = new Executor(Origin::NEW, $this->readConfig->new, $tracker, $this->trackLatency, $this->trackErrors, $payload);

        $result = match ($stage) {
            Stage::OFF => $old->run(),
            Stage::DUALWRITE => $old->run(),
            Stage::SHADOW => $this->readBoth($old, $new, $tracker),
            Stage::LIVE => $this->readBoth($new, $old, $tracker),
            Stage::RAMPDOWN => $new->run(),
            Stage::COMPLETE => $new->run(),
        };

        // TODO(sc-219377): Emit the event here

        return $result;
    }

    /**
     * Uses the provided flag key and context to execute a migration-backed write operation.
     */
    public function write(
        string $key,
        LDContext|LDUser $context,
        Stage $defaultStage,
        mixed $payload = null
    ): WriteResult {
        $variationResult = $this->client->migrationVariation($key, $context, $defaultStage);
        /** @var Stage */
        $stage = $variationResult['stage'];
        /** @var OpTracker */
        $tracker = $variationResult['tracker'];
        $tracker->operation(Operation::READ);

        $old = new Executor(Origin::OLD, $this->writeConfig->old, $tracker, $this->trackLatency, $this->trackErrors, $payload);
        $new = new Executor(Origin::NEW, $this->writeConfig->new, $tracker, $this->trackLatency, $this->trackErrors, $payload);

        $writeResult = match ($stage) {
            Stage::OFF => new WriteResult($old->run()),
            Stage::DUALWRITE => $this->writeBoth($old, $new, $tracker),
            Stage::SHADOW => $this->writeBoth($old, $new, $tracker),
            Stage::LIVE => $this->writeBoth($new, $old, $tracker),
            Stage::RAMPDOWN => $this->writeBoth($new, $old, $tracker),
            Stage::COMPLETE => new WriteResult($new->run()),
        };

        // TODO(sc-219377): Emit the event here

        return $writeResult;
    }

    private function readBoth(Executor $authoritative, Executor $nonauthoritative, OpTracker $tracker): OperationResult
    {
        // TODO(sc-219378): Add sampling to limit to 50% chance
        if ($this->executionOrder == ExecutionOrder::RANDOM) {
            $nonauthoritativeResult = $nonauthoritative->run();
            $authoritativeResult = $authoritative->run();
        } else {
            $authoritativeResult = $authoritative->run();
            $nonauthoritativeResult = $nonauthoritative->run();
        }

        if ($this->readConfig->comparison === null) {
            return $authoritativeResult;
        }

        if ($authoritativeResult->isSuccessful() && $nonauthoritativeResult->isSuccessful()) {
            $tracker->consistent(fn (): bool => ($this->readConfig->comparison)($authoritativeResult->value, $nonauthoritativeResult->value));
        }

        return $authoritativeResult;
    }

    private function writeBoth(Executor $authoritative, Executor $nonauthoritative, OpTracker $tracker): WriteResult
    {
        $authoritativeResult = $authoritative->run();
        $tracker->invoked($authoritative->origin);

        if (!$authoritativeResult->isSuccessful()) {
            return new WriteResult($authoritativeResult);
        }

        $nonauthoritativeResult = $nonauthoritative->run();
        $tracker->invoked($nonauthoritative->origin);

        return new WriteResult($authoritativeResult, $nonauthoritativeResult);
    }
}
