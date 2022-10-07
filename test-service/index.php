<?php

// This script is executed by the webserver for every request, regardless of the
// request path. We use Flight to route requests.

require_once 'vendor/autoload.php';

require_once 'SdkClientEntity.php';
require_once 'TestDataStore.php';
require_once 'TestService.php';


date_default_timezone_set('UTC');

$logger = new Monolog\Logger('testservice');
$logger->pushHandler(new Monolog\Handler\StreamHandler('php://stderr', Monolog\Logger::DEBUG));

$dataStorePath = getenv("LD_TEST_SERVICE_DATA_DIR");
if (!$dataStorePath) {
    $dataStorePath = '/tmp/php-server-sdk-test-service';
}
if (!is_dir($dataStorePath)) {
    if (!mkdir($dataStorePath, 0700, true)) {
        return false;
    }
}

$store = new TestDataStore($dataStorePath);
$service = new TestService($store, $logger);
$service->start();
