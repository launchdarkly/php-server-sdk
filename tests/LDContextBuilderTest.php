<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\LDContext;
use LaunchDarkly\LDContextBuilder;
use LaunchDarkly\Types\AttributeReference;
use PHPUnit\Framework\TestCase;

class LDContextBuilderTest extends TestCase
{
    private LDContextBuilder $builder;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = new LDContextBuilder('some-context-key');
    }

    public function testBuild(): void
    {
        $context = $this->builder->build();

        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-context-key',
                null,
                false,
                null,
                null,
                null,
                null
            ),
            $context
        );
    }

    public function testKey(): void
    {
        $builder = $this->builder->key('some-key');

        $this->assertEquals($this->builder, $builder);

        $context = $this->builder->build();
        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-key',
                null,
                false,
                null,
                null,
                null,
                null
            ),
            $context
        );
    }

    public function testKind(): void
    {
        $builder = $this->builder->kind('some-kind');

        $this->assertEquals($this->builder, $builder);

        $context = $this->builder->build();
        $this->assertEquals(
            new LDContext(
                'some-kind',
                'some-context-key',
                null,
                false,
                null,
                null,
                null,
                null
            ),
            $context
        );
    }

    public function testName(): void
    {
        $builder = $this->builder->name('some-name');

        $this->assertEquals($this->builder, $builder);

        $context = $this->builder->build();
        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-context-key',
                'some-name',
                false,
                null,
                null,
                null,
                null
            ),
            $context
        );

        $builder = $builder->name(null);

        $context = $builder->build();
        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-context-key',
                null,
                false,
                null,
                null,
                null,
                null
            ),
            $context
        );
    }

    public function testAnonymous(): void
    {
        $builder = $this->builder->anonymous(true);

        $this->assertEquals($this->builder, $builder);

        $context = $this->builder->build();
        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-context-key',
                null,
                true,
                null,
                null,
                null,
                null
            ),
            $context
        );
    }

    public function testSet(): void
    {
        $builder = $this->builder->set('attribute-name', 'value');

        $this->assertEquals($this->builder, $builder);

        $context = $this->builder->build();
        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-context-key',
                null,
                false,
                ['attribute-name' => 'value'],
                null,
                null,
                null
            ),
            $context
        );
    }

    /**
     * @dataProvider trySetDataProvider
     */
    public function testTrySet($expectedIsSet, $attributeName, $value, $expectedLdContext): void
    {
        $isSet = $this->builder->trySet($attributeName, $value);

        $this->assertEquals($expectedIsSet, $isSet);

        $context = $this->builder->build();
        $this->assertEquals(
            $expectedLdContext,
            $context
        );
    }

    public function testPrivate(): void
    {
        $builder = $this->builder->private(
            'string-attribute-ref',
            AttributeReference::fromPath('refPath'),
            AttributeReference::fromLiteral('attributeName')
        );
        $this->assertEquals($this->builder, $builder);

        $context = $this->builder->build();
        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-context-key',
                null,
                false,
                null,
                [
                    AttributeReference::fromPath('string-attribute-ref'),
                    AttributeReference::fromPath('refPath'),
                    AttributeReference::fromLiteral('attributeName')
                ],
                null,
                null
            ),
            $context
        );
    }

    public function testPrivateEmptyAttributeRefs(): void
    {
        $builder = $this->builder->private();
        $this->assertEquals($this->builder, $builder);

        $context = $this->builder->build();
        $this->assertEquals(
            new LDContext(
                LDContext::DEFAULT_KIND,
                'some-context-key',
                null,
                false,
                null,
                null,
                null,
                null
            ),
            $context
        );
    }

    public static function trySetDataProvider(): array
    {
        return [
            'key string' => [
                true,
                'key',
                'value',
                new LDContext(
                    LDContext::DEFAULT_KIND,
                    'value',
                    null,
                    false,
                    null,
                    null,
                    null,
                    null
                )
            ],
            'key not string' => [
                false,
                'key',
                12345,
                new LDContext(
                    LDContext::DEFAULT_KIND,
                    'some-context-key',
                    null,
                    false,
                    null,
                    null,
                    null,
                    null
                )
            ],
            'kind string' => [
                true,
                'kind',
                'value',
                new LDContext(
                    'value',
                    'some-context-key',
                    null,
                    false,
                    null,
                    null,
                    null,
                    null
                )
            ],
            'kind not string' => [
                false,
                'kind',
                12345,
                new LDContext(
                    LDContext::DEFAULT_KIND,
                    'some-context-key',
                    null,
                    false,
                    null,
                    null,
                    null,
                    null
                )
            ],
            'anonymous bool' => [
                true,
                'anonymous',
                true,
                new LDContext(
                    LDContext::DEFAULT_KIND,
                    'some-context-key',
                    null,
                    true,
                    null,
                    null,
                    null,
                    null
                )
            ],
            'anonymous not bool' => [
                false,
                'anonymous',
                12345,
                new LDContext(
                    LDContext::DEFAULT_KIND,
                    'some-context-key',
                    null,
                    false,
                    null,
                    null,
                    null,
                    null
                )
            ],
            'attributes value not null' => [
                true,
                'attribute-name',
                'value',
                new LDContext(
                    LDContext::DEFAULT_KIND,
                    'some-context-key',
                    null,
                    false,
                    ['attribute-name' => 'value'],
                    null,
                    null,
                    null
                )
            ],
            'attributes name value' => [
                true,
                'attribute-name',
                null,
                new LDContext(
                    LDContext::DEFAULT_KIND,
                    'some-context-key',
                    null,
                    false,
                    [],
                    null,
                    null,
                    null
                )
            ],
        ];
    }
}
