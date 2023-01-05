<?php

declare(strict_types=1);

// This script is executed by the webserver for every request, regardless of the
// request path. We use Flight to route requests.

require_once 'vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tests\TestDataStore;
use Tests\TestService;

date_default_timezone_set('UTC');

$logger = new Logger('testservice');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

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
