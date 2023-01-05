<?php

declare(strict_types=1);

namespace Tests;

use LaunchDarkly\Integrations\Guzzle;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SdkClientEntity
{
    private LDClient $_client;
    private Logger $_logger;

    public function __construct($params)
    {
        $tag = $params['tag'];

        $logger = new Logger('sdkclient');
        $stream = new StreamHandler('php://stderr', Logger::DEBUG);
        $stream->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: [$tag] %message%\n"
        ));
        $logger->pushHandler($stream);
        $this->_logger = $logger;

        $this->_client = self::createSdkClient($params, $logger);
    }

    public static function createSdkClient($params, $logger): LDClient
    {
        $config = $params['configuration'];

        $sdkKey = $config['credential'];
        $options = [
            'event_publisher' => Guzzle::eventPublisher(),
            'logger' => $logger
        ];

        $pollingConfig = $config['polling'] ?? [];
        $options['base_uri'] = $pollingConfig['baseUri'] ?? null;

        $options['send_events'] = ($config['events'] ?? null) !== null;
        $eventsConfig = $config['events'] ?? [];
        $options['events_uri'] = $eventsConfig['baseUri'] ?? null;
        $options['all_attributes_private'] = $eventsConfig['allAttributesPrivate'] ?? false;
        $options['private_attribute_names'] = $eventsConfig['globalPrivateAttributes'] ?? null;

        return new LDClient($sdkKey, $options);
    }

    public function close()
    {
        // there isn't really any cleanup to do
        $this->_logger->info('Test ended');
    }

    public function doCommand(mixed $reqParams): mixed
    {
        $command = $reqParams['command'];
        $commandParams = $reqParams[$command] ?? null;
        switch ($command) {
            case 'customEvent':
                $this->doCustomEvent($commandParams);
                return null;

            case 'evaluate':
                return $this->doEvaluate($commandParams);

            case 'evaluateAll':
                return $this->doEvaluateAll($commandParams);

            case 'identifyEvent':
                $this->doIdentifyEvent($commandParams);
                return null;

            case 'flushEvents':
                $this->_client->flush();
                return null;

            case 'secureModeHash':
                return $this->doSecureModeHash($commandParams);

            case 'contextBuild':
                return $this->doContextBuild($commandParams);
            
            case 'contextConvert':
                return $this->doContextConvert($commandParams);
            
            default:
                return false;  // means invalid command
        }
    }

    private function doCustomEvent(array $params): void
    {
        $this->_client->track(
            $params['eventKey'],
            $this->makeContext($params['context']),
            $params['data'] ?? null,
            $params['metricValue'] ?? null
        );
    }

    private function doEvaluate(array $params): array
    {
        $flagKey = $params['flagKey'];
        $context = LDContext::fromJson($params['context']);
        $defaultValue = $params['defaultValue'] ?? null;
        $detail = $params['detail'] ?? false;

        if ($detail) {
            $result = $this->_client->variationDetail($flagKey, $context, $defaultValue);
            return [
                "value" => $result->getValue(),
                "variationIndex" => $result->getVariationIndex(),
                "reason" => $result->getReason()
            ];
        } else {
            $value = $this->_client->variation($flagKey, $context, $defaultValue);
            return [
                "value" => $value
            ];
        }
    }

    private function doEvaluateAll(array $params): array
    {
        $options = [];
        foreach (['clientSideOnly', 'detailsOnlyForTrackedFlags', 'withReasons'] as $option) {
            if ($params[$option] ?: false) {
                $options[$option] = true;
            }
        }
        $context = LDContext::fromJson($params['context']);
        $state = $this->_client->allFlagsState($context, $options);
        return [
            'state' => $state->jsonSerialize()
        ];
    }

    private function doIdentifyEvent(array $params): void
    {
        $this->_client->identify($this->makeContext($params['context']));
    }

    private function doSecureModeHash(array $params): array
    {
        $context = $this->makeContext($params['context']);
        $result = $this->_client->secureModeHash($context);
        return [
            'result' => $result
        ];
    }

    private function doContextBuild(array $params): array
    {
        try {
            if ($params['multi'] ?? null) {
                $b = LDContext::multiBuilder();
                foreach ($params['multi'] as $mp) {
                    $b->add($this->buildSingleKind($mp));
                }
                $c = $b->build();
            } else {
                $c = $this->buildSingleKind($params['single']);
            }
            return $this->makeContextResponse($c);
        } catch (\Throwable $e) {
            return ['error' => "$e"];
        }
    }

    private function buildSingleKind(array $params): LDContext
    {
        $b = LDContext::builder($params['key'] ?? null);
        if (($params['kind'] ?? null) != null) {
            $b->kind($params['kind']);
        }
        $b->name($params['name'] ?? null)
            ->anonymous($params['anonymous'] ?? false);
        if ($params['custom'] ?? null) {
            foreach ($params['custom'] as $k => $v) {
                $b->set($k, $v);
            }
        }
        foreach ($params['private'] ?? [] as $p) {
            $b->private($p);
        }
        return $b->build();
    }

    private function makeContextResponse(LDContext $c): array
    {
        return $c->isValid() ? ['output' => json_encode($c)] : ['error' => $c->getError()];
    }

    private function doContextConvert(array $params): array
    {
        try {
            return $this->makeContextResponse(LDContext::fromJson($params['input']));
        } catch (\Throwable $e) {
            return ['error' => "$e"];
        }
    }

    private function makeContext(array $data): LDContext
    {
        return LDContext::fromJson($data);
    }
}
