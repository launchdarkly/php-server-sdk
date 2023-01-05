<?php

namespace LaunchDarkly\Tests\Types;

use LaunchDarkly\Types\AttributeReference;

class AttributeReferenceTest extends \PHPUnit\Framework\TestCase
{
    public function testSimplePath()
    {
        $name = 'attr';
        $a = AttributeReference::fromPath($name);
        self::assertNull($a->getError());
        self::assertEquals(1, $a->getDepth());
        self::assertEquals($name, $a->getComponent(0));
        self::assertEquals($name, $a->getPath());
    }

    public function testSimplePathWithSlashNotAtStart()
    {
        $name = 'attr/a~1';
        $a = AttributeReference::fromPath($name);
        self::assertNull($a->getError());
        self::assertEquals(1, $a->getDepth());
        self::assertEquals($name, $a->getComponent(0));
        self::assertEquals($name, $a->getPath());
    }

    public function testPathWithMultipleComponents()
    {
        $path = '/first/second/third';
        $a = AttributeReference::fromPath($path);
        self::assertNull($a->getError());
        self::assertEquals(3, $a->getDepth());
        self::assertEquals('first', $a->getComponent(0));
        self::assertEquals('second', $a->getComponent(1));
        self::assertEquals('third', $a->getComponent(2));
        self::assertEquals($path, $a->getPath());
    }

    public function testLiteral()
    {
        $name = 'attr';
        $a = AttributeReference::fromLiteral($name);
        self::assertNull($a->getError());
        self::assertEquals(1, $a->getDepth());
        self::assertEquals($name, $a->getComponent(0));
        self::assertEquals($name, $a->getPath());
    }

    public function testLiteralWithSpecialCharacters()
    {
        $name = '/attr~';
        $a = AttributeReference::fromLiteral($name);
        self::assertNull($a->getError());
        self::assertEquals(1, $a->getDepth());
        self::assertEquals($name, $a->getComponent(0));
        self::assertEquals('/~1attr~0', $a->getPath());
    }

    public function testErrorConditions()
    {
        foreach (['', '/', '//', '/a//b', '/a/b//', '/a~', '/a~2'] as $s) {
            $a = AttributeReference::fromPath($s);
            self::assertNotNull($a->getError());
        }
        self::assertNotNull(AttributeReference::fromLiteral('')->getError());
    }
}
