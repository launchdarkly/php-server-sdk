<?php

require_once 'SdkClientEntity.php';

class TestService
{
    private $_store;
    private $_logger;
    private $_app;

    public function __construct($store, $logger)
    {
        $this->_store = $store;
        $this->_logger = $logger;

        $this->_app = new flight\Engine();
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
            $params = $this->_app->request()->data;
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

    public function start()
    {
        $this->_app->start();
    }

    public function getStatus()
    {
        return [
            'name' => 'php-server-sdk',
            'capabilities' => [
                'php',
                'server-side',
                'all-flags-client-side-only',
                'all-flags-details-only-for-tracked-flags',
                'all-flags-with-reasons',
                'secure-mode-hash'
            ],
            'clientVersion' => \LaunchDarkly\LDClient::VERSION
        ];
    }

    public function createClient($params)
    {
        $this->_logger->info("Creating client with parameters: " . json_encode($params));

        $client = new SdkClientEntity($params, $this->_logger); // just to verify that the config is valid

        return $this->_store->addClientParams($params);
    }

    public function deleteClient($id)
    {
        $c = $this->getClient($id);
        if ($c) {
            $c->close();
            $this->_store->deleteClientParams($id);
            return true;
        }
        return false;
    }

    private function getClient($id)
    {
        $params = $this->_store->getClientParams($id);
        if ($params === null) {
            return null;
        }
        return new SdkClientEntity($params);
    }
}
