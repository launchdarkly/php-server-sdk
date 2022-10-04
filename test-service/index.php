<?php

require_once 'vendor/autoload.php';

require_once 'SdkClientEntity.php';
require_once 'TestDataStore.php';
require_once 'TestService.php';


date_default_timezone_set('UTC');

$logger = new Monolog\Logger('testservice');
$logger->pushHandler(new Monolog\Handler\StreamHandler('php://stderr', Monolog\Logger::DEBUG));

$store = new TestDataStore('/app/test-service/data-store');
$service = new TestService($store, $logger);
$service->start();
