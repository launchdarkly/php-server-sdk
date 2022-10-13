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
            $params = $this->_app->request()->data;
            $id = $this->createClient($params);
            header("Location:/clients/$id");
        });

        $this->_app->route('POST /clients/@id', function ($id) {
            $c = $this->getClient($id);
            if (!$c) {
                http_response_code(404);
                return;
            }
            $resp = $c->doCommand($this->_app->request()->data);
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
                'secure-mode-hash'
            ],
            'clientVersion' => \LaunchDarkly\LDClient::VERSION
        ];
    }

    public function createClient(mixed $params): string
    {
        $this->_logger->info("Creating client with parameters: " . json_encode($params));

        $client = new SdkClientEntity($params, $this->_logger); // just to verify that the config is valid

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
        return new SdkClientEntity($params);
    }
}
