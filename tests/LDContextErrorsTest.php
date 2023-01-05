<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\LDContext;

class LDContextErrorsTest extends \PHPUnit\Framework\TestCase
{
    public function testKeyEmptyString()
    {
        self::assertContextInvalid(LDContext::create(''));
        self::assertContextInvalid(LDContext::builder('')->build());
    }

    /**
     * @dataProvider kindBadStringValues
     */
    public function testKindInvalidStrings($value)
    {
        self::assertContextInvalid(LDContext::create('a', $value));
        self::assertContextInvalid(LDContext::builder('a')->kind($value)->build());
    }

    public function kindBadStringValues()
    {
        return [['kind'], ['multi'], ['b$c']];
    }

    public function testCreateMultiWithNoContexts()
    {
        self::assertContextInvalid(LDContext::createMulti());
    }

    public function testMultiBuilderWithNoContexts()
    {
        self::assertContextInvalid(LDContext::multiBuilder()->build());
    }

    public function testCreateMultiWithDuplicateKind()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('b', 'kind1');
        self::assertContextInvalid(LDContext::createMulti($c1, $c2));
    }

    public function testMultiBuilderWithDuplicateKind()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('b', 'kind1');
        self::assertContextInvalid(LDContext::multiBuilder()->add($c1)->add($c2)->build());
    }

    public function testCreateMultiWithInvalidContext()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('');
        self::assertContextInvalid(LDContext::createMulti($c1, $c2));
    }

    public function testMultiBuilderWithInvalidContext()
    {
        $c1 = LDContext::create('a', 'kind1');
        $c2 = LDContext::create('');
        self::assertContextInvalid(LDContext::multiBuilder()->add($c1)->add($c2)->build());
    }

    private function assertContextInvalid($c)
    {
        self::assertFalse($c->isValid());
        self::assertNotNull($c->getError());
    }
}
