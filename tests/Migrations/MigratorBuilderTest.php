<?php

namespace LaunchDarkly\Tests\Migrations;

use Closure;
use LaunchDarkly\LDClient;
use LaunchDarkly\Migrations\ExecutionOrder;
use LaunchDarkly\Migrations\Migrator;
use LaunchDarkly\Migrations\MigratorBuilder;
use LaunchDarkly\Types\Result;

class MigratorBuilderTest extends \PHPUnit\Framework\TestCase
{
    protected MigratorBuilder $builder;
    protected Closure $noop;

    protected function setUp(): void
    {
        $client = new LDClient('key');
        $this->builder = new MigratorBuilder($client);
        $this->noop = fn () => Result::success(null);
    }

    public function testCanBuildSuccessfully(): void
    {
        $this->builder->read(
            fn () => Result::success('old origin'),
            fn () => Result::success('new origin'),
        );
        $this->builder->write(
            fn () => Result::success('old origin'),
            fn () => Result::success('new origin'),
        );
        $result = $this->builder->build();

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Migrator::class, $result->value);
    }

    public function orderProvider(): array
    {
        return [
            [ExecutionOrder::SERIAL],
            [ExecutionOrder::RANDOM],
        ];
    }

    /**
     * @dataProvider orderProvider
     */
    public function testCanModifyExecutionOrder(ExecutionOrder $order): void
    {
        $this->builder->read($this->noop, $this->noop);
        $this->builder->write($this->noop, $this->noop);
        $this->builder->readExecutionOrder($order);

        $result = $this->builder->build();

        $this->assertTrue($result->isSuccessful());
        $this->assertInstanceOf(Migrator::class, $result->value);
    }

    public function testFailsWithoutRead(): void
    {
        $this->builder->write($this->noop, $this->noop);
        $result = $this->builder->build();

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('read configuration not provided', $result->error);
    }

    public function testFailsWithoutWrite(): void
    {
        $this->builder->read($this->noop, $this->noop);
        $result = $this->builder->build();

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('write configuration not provided', $result->error);
    }
}
