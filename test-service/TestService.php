<?php

declare(strict_types=1);

namespace Tests;

use flight\Engine;
use Monolog\Logger;

class TestService
{
    private TestDataStore $_store;
    private Logger $_logger;
    private Engine $_app;

    public function __construct(TestDataStore $store, Logger $logger)
    {
        $this->_store = $store;
        $this->_logger = $logger;

        $this->_app = new Engine();
        $this->_app->set('flight.log_errors', true);

        $this->_app->route('GET /', function () {
            $this->_app->json($this->getStatus());
        });

        $this->_app->route('POST /', function () {
            $params = self::parseRequestJson($this->_app->request()->getBody());
            $id = $this->createClient($params);
            header("Location:/clients/$id");
        });

        $this->_app->route('DELETE /', function () {
            $this->_logger->info('Test harness has told us to quit');
        });

        $this->_app->route('POST /clients/@id', function ($id) {
            $c = $this->getClient($id);
            if (!$c) {
                http_response_code(404);
                return;
            }
            $params = self::parseRequestJson($this->_app->request()->getBody());
            $resp = $c->doCommand($params);
            if ($resp === false) {
                http_response_code(400);
            } elseif (is_array($resp)) {
                $this->_app->json($resp);
            }
        });

        $this->_app->route("DELETE /clients/@id", function ($id) {
            if (!$this->deleteClient($id)) {
                http_response_code(404);
            }
        });
    }

    public function start(): void
    {
        $this->_app->start();
    }

    public function getStatus(): array
    {
        return [
            'name' => 'php-server-sdk',
            'capabilities' => [
                'php',
                'server-side',
                'all-flags-client-side-only',
                'all-flags-details-only-for-tracked-flags',
                'all-flags-with-reasons',
                'context-type',
                'secure-mode-hash',
                'migrations',
                'event-sampling',
                'inline-context',
                'anonymous-redaction',
                'client-prereq-events',
                'big-segments'
            ],
            'clientVersion' => \LaunchDarkly\LDClient::VERSION
        ];
    }

    public function createClient(mixed $params): string
    {
        $this->_logger->info("Creating client with parameters: " . json_encode($params));

        $client = new SdkClientEntity($params, true, $this->_logger); // just to verify that the config is valid

        return $this->_store->addClientParams($params);
    }

    public function deleteClient(string $id): bool
    {
        $c = $this->getClient($id);
        if ($c) {
            $c->close();
            $this->_store->deleteClientParams($id);
            return true;
        }
        return false;
    }

    private function getClient(string $id): ?SdkClientEntity
    {
        $params = $this->_store->getClientParams($id);
        if ($params === null) {
            return null;
        }

        return new SdkClientEntity($params, false);
    }

    // The following methods for normalizing parsed JSON are a workaround for PHP's inability to distinguish
    // between an empty JSON array [] and an empty JSON object {} if you parse JSON into associative arrays.
    // In order for some contract tests to work which involve empty object values, we need to be able to
    // make such a distinction. But, we don't want to parse all of the JSON parameters as objects, because
    // associative arrays are much more convenient for most of our logic. The solution is to parse everything
    // as an object first, then convert every object to an array UNLESS it is an empty object.

    private static function parseRequestJson(string $json): array
    {
        return self::normalizeParsedData(json_decode($json));
    }

    private static function normalizeParsedData(mixed $value): mixed
    {
        if (is_array($value)) {
            $ret = [];
            foreach ($value as $element) {
                $ret[] = self::normalizeParsedData($element);
            }
            return $ret;
        }
        if (!is_object($value)) {
            return $value;
        }
        $props = get_object_vars($value);
        if (count($props) === 0) {
            return $value;
        }
        $ret = [];
        foreach ($props as $k => $v) {
            $ret[$k] = self::normalizeParsedData($v);
        }
        return $ret;
    }
}
