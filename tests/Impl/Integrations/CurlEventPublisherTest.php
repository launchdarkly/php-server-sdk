<?php

namespace LaunchDarkly\Tests\Impl\Integrations;

use LaunchDarkly\Impl\Integrations\CurlEventPublisher;
use PHPUnit\Framework\TestCase;

class CurlEventPublisherTest extends TestCase
{
    private const PAYLOAD = '{"kind":"bulk","events":[{"kind":"identify","key":"user with spaces"}]}';

    private string $_workDir;

    public function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped("These tests use a shell script to stand in for curl");
        }

        $workDir = sys_get_temp_dir() . '/ld-curl-test-' . uniqid();
        mkdir($workDir . '/record', 0700, true);
        mkdir($workDir . '/payloads', 0700, true);

        // tempnam() resolves the directory it is given, and the temporary directory on macOS is
        // reached through a symbolic link. Resolve the path here so that the tests compare it to
        // the path that the publisher reports.
        $this->_workDir = realpath($workDir);

        // This script records the arguments it receives, and a copy of any file that curl would
        // read the payload from. The publisher runs the request in the background, so the tests
        // wait for the arguments file to appear.
        $script = "#!/bin/sh\n"
            . "DEST=" . escapeshellarg($this->_workDir . '/record') . "\n"
            . "for a in \"\$@\"; do\n"
            . "  case \"\$a\" in\n"
            . "    @*) cp \"\${a#@}\" \"\$DEST/payload\" ;;\n"
            . "  esac\n"
            . "done\n"
            . "for a in \"\$@\"; do printf '%s\\n' \"\$a\"; done > \"\$DEST/args.tmp\"\n"
            . "mv \"\$DEST/args.tmp\" \"\$DEST/args\"\n";

        file_put_contents($this->_workDir . '/curl', $script);
        chmod($this->_workDir . '/curl', 0700);
    }

    public function tearDown(): void
    {
        if (!isset($this->_workDir)) {
            return;
        }

        foreach (glob($this->_workDir . '/{,*/}*', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach ([$this->_workDir . '/record', $this->_workDir . '/payloads', $this->_workDir] as $dir) {
            @rmdir($dir);
        }
    }

    private function makePublisher(array $options = []): CurlEventPublisher
    {
        return new CurlEventPublisher('sdk-key', array_merge([
            'events_uri' => 'http://localhost:8080',
            'timeout' => 3,
            'connect_timeout' => 3,
            'curl' => $this->_workDir . '/curl',
        ], $options));
    }

    /**
     * @return string[] the arguments that the stand-in for curl received
     */
    private function awaitRecordedArgs(): array
    {
        $path = $this->_workDir . '/record/args';

        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline) {
            // PHP caches the result of a failed stat, so the loop must discard it to see the file.
            clearstatcache(true, $path);
            if (file_exists($path)) {
                return explode("\n", rtrim(file_get_contents($path), "\n"));
            }
            usleep(1000);
        }

        $this->fail("The request did not run within the timeout");
    }

    /**
     * @return ?string the argument that names the file holding the payload
     */
    private function payloadFileArg(array $args): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, '@')) {
                return substr($arg, 1);
            }
        }

        return null;
    }

    public function testPassesPayloadOnCommandLineByDefault()
    {
        $before = glob(sys_get_temp_dir() . '/ld-*') ?: [];

        $this->assertTrue($this->makePublisher()->publish(self::PAYLOAD));

        $args = $this->awaitRecordedArgs();
        $this->assertContains('-d', $args);
        $this->assertContains(self::PAYLOAD, $args);
        $this->assertNotContains('--data-binary', $args);
        $this->assertNull($this->payloadFileArg($args));

        // Without the option, the publisher must not touch the filesystem at all.
        $this->assertEquals($before, glob(sys_get_temp_dir() . '/ld-*') ?: []);
    }

    public function testWritesPayloadToNamedDirectory()
    {
        $publisher = $this->makePublisher(['payload_temp_dir' => $this->_workDir . '/payloads']);
        $this->assertTrue($publisher->publish(self::PAYLOAD));

        $args = $this->awaitRecordedArgs();
        $this->assertContains('--data-binary', $args);
        $this->assertNotContains('-d', $args);

        $payloadFile = $this->payloadFileArg($args);
        $this->assertNotNull($payloadFile);
        $this->assertEquals($this->_workDir . '/payloads', dirname($payloadFile));
        $this->assertEquals(self::PAYLOAD, file_get_contents($this->_workDir . '/record/payload'));
    }

    public function testUsesSystemTempDirectoryWhenOptionIsTrue()
    {
        $this->assertTrue($this->makePublisher(['payload_temp_dir' => true])->publish(self::PAYLOAD));

        $payloadFile = $this->payloadFileArg($this->awaitRecordedArgs());
        $this->assertNotNull($payloadFile);
        $this->assertEquals(realpath(sys_get_temp_dir()), realpath(dirname($payloadFile)));
        $this->assertEquals(self::PAYLOAD, file_get_contents($this->_workDir . '/record/payload'));
    }

    public function testRemovesPayloadFileAfterTheRequest()
    {
        $dir = $this->_workDir . '/payloads';
        $this->assertTrue($this->makePublisher(['payload_temp_dir' => $dir])->publish(self::PAYLOAD));

        $this->awaitRecordedArgs();

        // The removal is chained after the request, so it can lag behind the recorded arguments.
        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline && (glob($dir . '/ld-*') ?: [])) {
            usleep(1000);
        }

        $this->assertEmpty(glob($dir . '/ld-*') ?: []);
    }
}
