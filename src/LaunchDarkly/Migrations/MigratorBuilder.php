<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use Closure;
use LaunchDarkly\LDClient;
use LaunchDarkly\Types\Result;

/**
 * The migration builder is used to configure and construct an instance of a
 * {@see Migrator}. This migrator can be used to perform LaunchDarkly assisted
 * technology migrations through the use of migration-based feature flags.
 */
class MigratorBuilder
{
    # Defaults as required by the spec. Since PHP does not support parallelism,
    # we default to serial.
    private ExecutionOrder $readExecutionOrder = ExecutionOrder::SERIAL;
    private bool $trackLatency = true;
    private bool $trackErrors = true;

    private ?MigrationConfig $readConfig = null;
    private ?MigrationConfig $writeConfig = null;

    public function __construct(private LDClient $client)
    {
    }

    /**
     * The read execution order influences the parallelism and execution order
     * for read operations involving multiple origins.
     */
    public function readExecutionOrder(ExecutionOrder $order): MigratorBuilder
    {
        $this->readExecutionOrder = $order;
        return $this;
    }

    /**
     * Enable or disable latency tracking for migration operations. This
     * latency information can be sent upstream to LaunchDarkly to enhance
     * migration visibility.
     */
    public function trackLatency(bool $track): MigratorBuilder
    {
        $this->trackLatency = $track;
        return $this;
    }

    /**
     * Enable or disable error tracking for migration operations. This error
     * information can be sent upstream to LaunchDarkly to enhance migration
     * visibility.
     */
    public function trackErrors(bool $track): MigratorBuilder
    {
        $this->trackErrors = $track;
        return $this;
    }

    /**
     * Read can be used to configure the migration-read behavior of the
     * resulting migrator instance.
     *
     * Users are required to provide two different read methods -- one to read
     * from the old migration origin, and one to read from the new origin.
     * Additionally, customers can opt-in to consistency tracking by providing
     * a comparison function.
     *
     * Depending on the migration stage, one or both of these read methods may
     * be called.
     *
     * The read methods should accept a single nullable parameter. This
     * parameter is a payload passed through the {@see Migrator.read()} method.
     * This method should return a {@see Result} instance.
     *
     * The consistency method should accept 2 parameters of any type. These
     * parameters are the results of executing the read operation against the
     * old and new origins. If both operations were successful, the
     * consistency method will be invoked. This method should return true if
     * the two parameters are equal, or false otherwise.
     *
     * @param Closure(mixed): Result $old
     * @param Closure(mixed): Result $new
     * @param Closure(mixed,mixed): bool $comparison
     */
    public function read(Closure $old, Closure $new, ?Closure $comparison = null): MigratorBuilder
    {
        $this->readConfig = new MigrationConfig($old, $new, $comparison);
        return $this;
    }

    /**
     * Write can be used to configure the migration-write behavior of the
     * resulting :class:`Migrator` instance.
     *
     * Users are required to provide two different write methods -- one to
     * write to the old migration origin, and one to write to the new origin.
     *
     * Depending on the migration stage, one or both of these write methods may
     * be called.
     *
     * The write methods should accept a single nullable parameter. This
     * parameter is a payload passed through the {@see Migrator.write()} method.
     * This method should return a {@see Result} instance.
     *
     * @param Closure(mixed): Result $old
     * @param Closure(mixed): Result $new
     */
    public function write(Closure $old, Closure $new): MigratorBuilder
    {
        $this->writeConfig = new MigrationConfig($old, $new);
        return $this;
    }

    /**
     * Build constructs a Migrator instance to support migration-based
     * reads and writes.
     */
    public function build(): Result
    {
        if ($this->readConfig === null) {
            return Result::error('read configuration not provided');
        }

        if ($this->writeConfig === null) {
            return Result::error('write configuration not provided');
        }

        return Result::success(
            new Migrator(
                $this->client,
                $this->readExecutionOrder,
                $this->readConfig,
                $this->writeConfig,
                $this->trackLatency,
                $this->trackErrors,
            )
        );
    }
}
