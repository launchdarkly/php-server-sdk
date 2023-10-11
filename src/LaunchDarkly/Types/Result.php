<?php

declare(strict_types=1);

namespace LaunchDarkly\Types;

use Exception;

/**
 * A Result is used to reflect the outcome of any operation.
 *
 * Results can either be considered a success or a failure.
 *
 * In the event of success, the Result will contain an optional, nullable value
 * to hold any success value back to the calling function.
 *
 * If the operation fails, the Result will contain an error describing the
 * value. An optional exception may also be provided.
 */
final class Result
{
    /**
     * This constructor should be considered private. Consumers of this class
     * should use one of the two factory methods provided. Direct
     * instantiation should follow the below expectations:
     *
     * - Successful operations have contain a value, but *MUST NOT* contain an
     *   error or an exception value.
     * - Failed operations *MUST* contain an error string, and may optionally
     *   include an exception.
     */
    private function __construct(
        public readonly mixed $value,
        public readonly ?string $error = null,
        public readonly ?Exception $exception = null
    ) {
    }

    /**
     * Construct a successful result containing the provided value.
     */
    public static function success(mixed $value): Result
    {
        return new Result($value);
    }

    /**
     * Construct a failed result containing an error description and optional exception.
     */
    public static function error(string $error, ?Exception $exception = null): Result
    {
        return new Result(null, $error, $exception);
    }

    /**
     * Determine whether this result represents success or failure by checking for the presence of an error.
     */
    public function isSuccessful(): bool
    {
        return $this->error === null;
    }
}
