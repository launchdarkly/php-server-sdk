<?php

declare(strict_types=1);

namespace LaunchDarkly\Integrations\TestData;

class MigrationSettingsBuilder
{
    protected ?int $checkRatio = null;

    public function setCheckRatio(int $checkRatio): MigrationSettingsBuilder
    {
        $this->checkRatio = $checkRatio;
        return $this;
    }

    /**
     * Creates an associative array representation of the migration settings
     *
     * @return array the array representation of the migration settings
     */
    public function build(): array
    {
        return [
            "checkRatio" => $this->checkRatio,
        ];
    }
}
