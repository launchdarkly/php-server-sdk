<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use Exception;
use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\Impl;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDContext;
use Psr\Log\LoggerInterface;

/**
 * An OpTracker is responsible for managing the collection of measurements that
 * which a user might wish to record throughout a migration-assisted operation.
 *
 * Example measurements include latency, errors, and consistency.
 *
 * The OpTracker is not expected to be instantiated directly. Consumers should
 * instead call {@see \LaunchDarkly\LDClient:migration_variation()} and use the
 * returned tracker instance.
 */
class OpTracker
{
    private ?Operation $operation = null;
    private array $invoked = [];
    private ?bool $consistent = null;
    private int $consistentRatio = 1;
    private array $errors = [];
    private array $latencies = [];

    public function __construct(
        private LoggerInterface $logger,
        private string $key,
        private ?Impl\Model\FeatureFlag $flag,
        private LDContext $context,
        private EvaluationDetail $detail,
        private Stage $default_stage
    ) {
        $this->consistentRatio = $flag?->getMigrationSettings()?->getCheckRatio() ?? 1;
    }



    /**
     * Sets the migration related Operation associated with these tracking measurements.
     */
    public function operation(Operation $operation): OpTracker
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * Allows recording which {@see Origin}s were called during a migration.
     */
    public function invoked(Origin $origin): OpTracker
    {
        $this->invoked[$origin->value] = true;
        return $this;
    }


    /**
    * Allows recording the results of a consistency check.
    *
    * This method accepts a callable which should take no parameters and return
    * a single boolean to represent the consistency check results for a read
    * operation.
    *
    * A callable is provided in case sampling rules do not require consistency
    * checking to run. In this case, we can avoid the overhead of a function by
    * not using the callable.
    *
    * @param callable $isConsistent Callable that accepts 0 parameters and must return a boolean
    */
    public function consistent(callable $isConsistent): OpTracker
    {
        if (!Util::sample($this->consistentRatio)) {
            return $this;
        }

        try {
            $this->consistent = boolval($isConsistent());
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $this->logger->error("exception raised during consistency check $msg; failed to record measurement");
        }

        return $this;
    }

    /**
    * Allows recording whether an error occurred during the operation.
    */
    public function error(Origin $origin): OpTracker
    {
        $this->errors[$origin->value] = true;
        return $this;
    }


    /**
     * Allows tracking the recorded latency for an individual operation.
     */
    public function latency(Origin $origin, float $elapsedMs): OpTracker
    {
        $this->latencies[$origin->value] = $elapsedMs;
        return $this;
    }


    /**
    * Returns an array representing a migration operation event.
    *
    * This event data can be provided to {@see
    * \LaunchDarkly\LDClient::trackMigrationOp()} to relay this metric
    * information upstream to LaunchDarkly services.
    *
    * @return array<string, mixed>|string
    */
    public function build(): array|string
    {
        if (!$this->operation) {
            return "operation not provided";
        } elseif (strlen($this->key) === 0) {
            return "migration operation cannot contain an empty key";
        } elseif (count($this->invoked) === 0) {
            return "no origins were invoked";
        } elseif (!$this->context->isValid()) {
            return "provided context was invalid";
        }

        $error = $this->checkInvokedConsistency();
        if ($error !== null) {
            return $error;
        }

        $event = [
            'kind' => 'migration_op',
            'creationDate' => Util::currentTimeUnixMillis(),
            'contextKeys' => $this->context->getKeys(),
            'operation' => $this->operation->value,
            'evaluation' => [
                'key' => $this->key,
                'value' => $this->detail->getValue(),
                'default' => $this->default_stage->value,
                'reason' => $this->detail->getReason()->jsonSerialize(),
            ],

            'measurements' => [
                [
                    'key' => 'invoked',
                    'values' => $this->invoked,
                ]
            ],
        ];

        if ($this->flag) {
            $event['evaluation']['version'] = $this->flag->getVersion();

            if ($this->flag->getSamplingRatio() !== 1) {
                $event['samplingRatio'] = $this->flag->getSamplingRatio();
            }
        }

        if ($this->detail->getVariationIndex() !== null) {
            $event['evaluation']['variation'] = $this->detail->getVariationIndex();
        }

        if ($this->consistent !== null) {
            $measurement = [
                'key' => 'consistent',
                'value' => $this->consistent,
            ];

            if ($this->consistentRatio !== 1) {
                $measurement['samplingRatio'] = $this->consistentRatio;
            }

            $event['measurements'][] = $measurement;
        }

        if (count($this->errors)) {
            $event['measurements'][] = [
                'key' => 'error',
                'values' => $this->errors,
            ];
        }

        if (count($this->latencies)) {
            $event['measurements'][] = [
                'key' => 'latency_ms',
                'values' => $this->latencies,
            ];
        }

        return $event;
    }

    private function checkInvokedConsistency(): ?string
    {
        foreach (Origin::cases() as $origin) {
            $originValue = $origin->value;
            if (isset($this->invoked[$originValue])) {
                continue;
            }

            if (isset($this->latencies[$originValue])) {
                return "provided latency for origin {$originValue} without recording invocation";
            }

            if (isset($this->errors[$originValue])) {
                return "provided error for origin {$originValue} without recording invocation";
            }
        }

        if ($this->consistent !== null && count($this->invoked) !== 2) {
            return "provided consistency without recording both invocations";
        }

        return null;
    }
}
