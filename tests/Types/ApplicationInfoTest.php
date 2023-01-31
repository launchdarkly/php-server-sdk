<?php

namespace LaunchDarkly\Tests\Types;

use LaunchDarkly\Types\ApplicationInfo;
use PHPUnit\Framework\TestCase;

class ApplicationInfoTest extends TestCase
{
    /** @var ApplicationInfo */
    private $appInfo;

    public function setUp(): void
    {
        $this->appInfo = new ApplicationInfo();
    }

    public function testNewInstanceIsEmpty(): void
    {
        $this->assertEquals((string) $this->appInfo, "", "Empty app info isn't empty!");
    }

    public function testCanSetValuesAsExpected(): void
    {
        $this->appInfo
             ->withId("my-id")
             ->withVersion("my-version");

        $this->assertEquals("application-id/my-id application-version/my-version", (string) $this->appInfo, "Failed to set id and version correctly");
        $this->assertEmpty($this->appInfo->errors());
    }

    public function testIgnoresEmptyValues(): void
    {
        $this->appInfo->withId("")->withVersion("");

        $this->assertEquals("", (string) $this->appInfo, "Failed to set id and version correctly");
        $this->assertEquals([], $this->appInfo->errors(), "Failed to set id and version correctly");
    }

    /**
     * @return array<int,array<int,string>>
     */
    public function invalidValues(): array
    {
        return [
            [' ', 'Application value for %s contained invalid characters and was discarded'],
            [' ', 'Application value for %s contained invalid characters and was discarded'],
            ['@', 'Application value for %s contained invalid characters and was discarded'],
            ['@', 'Application value for %s contained invalid characters and was discarded'],
            ['abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-_a', 'Application value for %s was longer than 64 characters and was discarded'], // Too long
        ];
    }

    /**
     * @dataProvider invalidValues
     */
    public function testIgnoresInvalidValuesAndLogsAppropriately(string $value, string $error): void
    {
        $this->appInfo->withId($value)->withVersion($value);

        $errors = [
            sprintf($error, 'id'),
            sprintf($error, 'version'),
        ];

        $this->assertEquals("", (string) $this->appInfo, "Failed to set id and version correctly");
        $this->assertEquals($errors, $this->appInfo->errors(), "Failed to set id and version correctly");
    }

    public function testOnlyTracksMostRecentFailure(): void
    {
        $this->appInfo->withId('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.-_a');
        $this->assertEquals(["Application value for id was longer than 64 characters and was discarded"], $this->appInfo->errors(), "Most recent error wasn't retained");

        $this->appInfo->withId('@');
        $this->assertEquals(["Application value for id contained invalid characters and was discarded"], $this->appInfo->errors(), "Most recent error wasn't retained");
    }
}
