<?php

namespace LaunchDarkly\Tests\Impl;

use LaunchDarkly\Impl\Util;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    public function testSimpleSamplingCases(): void
    {
        $this->assertTrue(Util::sample(1));
        $this->assertFalse(Util::sample(0));
    }

    public function testNonTrivialSamplingRatio(): void
    {
        // Seed to control randomness.
        mt_srand(0);

        $counts = array_reduce(
            range(1, 1_000),
            fn (int $carry, int $mixed): int => Util::sample(2) ? ++$carry : $carry,
            0
        );

        $this->assertEquals(504, $counts);
    }

    public function testEventHeaderValuesAreStrings(): void
    {
        // guzzlehttp/guzzle 7.11+ deprecates non-string header values and
        // guzzle 8.0 will reject them, so every event header value must be a
        // string. See https://github.com/launchdarkly/php-server-sdk/issues/246
        $headers = Util::eventHeaders('sdk-key', []);

        foreach ($headers as $name => $value) {
            $this->assertIsString($value, "header '$name' should be a string");
        }
    }
}
