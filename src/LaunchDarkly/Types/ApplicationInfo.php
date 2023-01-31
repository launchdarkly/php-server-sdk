<?php

namespace LaunchDarkly\Types;

/**
 * An object that allows configuration of application metadata.
 *
 * Application metadata may be used in LaunchDarkly analytics or other product
 * features, but does not affect feature flag evaluations.
 *
 * To use these properties, provide an instance of ApplicationInfo in the config
 * parameter of the LDClient.
 *
 * Application values have the following restrictions:
 * - Cannot be empty
 * - Cannot exceed 64 characters in length
 * - Can only contain a-z, A-Z, 0-9, period (.), dash (-), and underscore (_).
 */
final class ApplicationInfo
{
    /** @var string|null **/
    private $id;

    /** @var string|null **/
    private $version;

    /** @var array **/
    private $errors;

    public function __construct()
    {
        $this->id = null;
        $this->version = null;
        $this->errors = [];
    }

    /**
     * Set the application id metadata identifier.
     */
    public function withId(string $id): ApplicationInfo
    {
        $this->id = $this->validateValue($id, 'id');

        return $this;
    }

    /**
     * Set the application version metadata identifier.
     */
    public function withVersion(string $version): ApplicationInfo
    {
        $this->version = $this->validateValue($version, 'version');

        return $this;
    }

    /**
     * Retrieve any validation errors that have accumulated as a result of creating this instance.
     */
    public function errors(): array
    {
        return array_values($this->errors);
    }

    public function __toString(): string
    {
        $parts = [];

        if ($this->id !== null) {
            $parts[] = "application-id/{$this->id}";
        }

        if ($this->version !== null) {
            $parts[] = "application-version/{$this->version}";
        }

        return join(" ", $parts);
    }

    private function validateValue(string $value, string $label): ?string
    {
        $value = strval($value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) > 64) {
            $this->errors[$label] = "Application value for $label was longer than 64 characters and was discarded";
            return null;
        }

        if (preg_match('/[^a-zA-Z0-9._-]/', $value)) {
            $this->errors[$label] = "Application value for $label contained invalid characters and was discarded";
            return null;
        }

        return $value;
    }
}
