<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
use LaunchDarkly\LDUser;
use LaunchDarkly\Types\Result;

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
        private bool $measureLatency,
        private bool $measureErrors,
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
        // TODO(sc-219376): Implement later

        return new OperationResult(Origin::OLD, Result::success(null));
    }

    /**
     * Uses the provided flag key and context to execute a migration-backed write operation.
     */
    public function write(
        string $key,
        LDContext|LDUser $context,
        Stage $defaultStage,
        mixed $payload = null
    ): OperationResult {
        // TODO(sc-219376): Implement later
        //
        return new OperationResult(Origin::OLD, Result::success(null));
    }
}
