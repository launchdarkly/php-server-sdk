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
}
