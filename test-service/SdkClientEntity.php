<?php

use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;

class SdkClientEntity
{
    private $_client;
    private $_logger;

    public function __construct($params)
    {
        $tag = $params['tag'];

        $logger = new Monolog\Logger('sdkclient');
        $stream = new Monolog\Handler\StreamHandler('php://stderr', Monolog\Logger::DEBUG);
        $stream->setFormatter(new Monolog\Formatter\LineFormatter(
            "[%datetime%] %channel%.%level_name%: [$tag] %message%\n"
        ));
        $logger->pushHandler($stream);
        $this->_logger = $logger;

        $this->_client = self::createSdkClient($params, $logger);
    }

    public static function createSdkClient($params, $logger)
    {
        $config = $params['configuration'];

        $sdkKey = $config['credential'];
        $options = [
            'event_publisher' => LaunchDarkly\Integrations\Guzzle::eventPublisher(),
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

    public function doCommand($reqParams)
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

    private function doCustomEvent($params)
    {
        $this->_client->track(
            $params['eventKey'],
            $this->makeUser($params['user']),
            $params['data'] ?? null,
            $params['metricValue'] ?? null
        );
    }

    private function doEvaluate($params)
    {
        $flagKey = $params['flagKey'];
        $user = $this->makeUser($params['user']);
        $defaultValue = $params['defaultValue'] ?? null;
        $detail = $params['detail'] ?? false;

        if ($detail) {
            $result = $this->_client->variationDetail($flagKey, $user, $defaultValue);
            return [
                "value" => $result->getValue(),
                "variationIndex" => $result->getVariationIndex(),
                "reason" => $result->getReason()
            ];
        } else {
            $value = $this->_client->variation($flagKey, $user, $defaultValue);
            return [
                "value" => $value
            ];
        }
    }

    private function doEvaluateAll($params)
    {
        $options = [];
        foreach (['clientSideOnly', 'detailsOnlyForTrackedFlags', 'withReasons'] as $option) {
            if ($params[$option] ?: false) {
                $options[$option] = true;
            }
        }
        $state = $this->_client->allFlagsState($this->makeUser($params['user']), $options);
        return [
            'state' => $state->jsonSerialize()
        ];
    }

    private function doIdentifyEvent($params)
    {
        $this->_client->identify($this->makeUser($params['user']));
    }

    private function doSecureModeHash($params)
    {
        $user = $this->makeUser($params['user']);
        $result = $this->_client->secureModeHash($user);
        return [
            'result' => $result
        ];
    }

    private function doContextBuild($params)
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
        } catch (Throwable $e) {
            return ['error' => "$e"];
        }
    }

    private function buildSingleKind($params)
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

    private function makeContextResponse($c)
    {
        return $c->isValid() ? ['output' => json_encode($c)] : ['error' => $c->getError()];
    }

    private function doContextConvert($params)
    {
        try {
            return $this->makeContextResponse(LDContext::fromJson($params['input']));
        } catch (Throwable $e) {
            return ['error' => "$e"];
        }
    }

    private function makeContext($data)
    {
        $privateAttributeNames = $data['privateAttributeNames'] ?? [];

        $builder = new LaunchDarkly\LDUserBuilder(isset($data['key']) ? $data['key'] : null);

        $secondary = $data['secondary'] ?? null;
        if (in_array('secondary', $privateAttributeNames)) {
            $builder->privateSecondary($secondary);
        } else {
            $builder->secondary($secondary);
        }

        $ip = $data['ip'] ?? null;
        if (in_array('ip', $privateAttributeNames)) {
            $builder->privateIp($ip);
        } else {
            $builder->ip($ip);
        }

        $country = $data['country'] ?? null;
        if (in_array('country', $privateAttributeNames)) {
            $builder->privateCountry($country);
        } else {
            $builder->country($country);
        }

        $email = $data['email'] ?? null;
        if (in_array('email', $privateAttributeNames)) {
            $builder->privateEmail($email);
        } else {
            $builder->email($email);
        }

        $name = $data['name'] ?? null;
        if (in_array('name', $privateAttributeNames)) {
            $builder->privateName($name);
        } else {
            $builder->name($name);
        }

        $avatar = $data['avatar'] ?? null;
        if (in_array('avatar', $privateAttributeNames)) {
            $builder->privateAvatar($avatar);
        } else {
            $builder->avatar($avatar);
        }

        $firstName = $data['firstName'] ?? null;
        if (in_array('firstName', $privateAttributeNames)) {
            $builder->privateFirstName($firstName);
        } else {
            $builder->firstName($firstName);
        }

        $lastName = $data['lastName'] ?? null;
        if (in_array('lastName', $privateAttributeNames)) {
            $builder->privateLastName($lastName);
        } else {
            $builder->lastName($lastName);
        }

        if (isset($data['anonymous'])) {
            $builder->anonymous($data['anonymous']);
        }

        if (isset($data['custom'])) {
            foreach ($data['custom'] as $key => $value) {
                if (in_array($key, $privateAttributeNames)) {
                    $builder->privateCustomAttribute($key, $value);
                } else {
                    $builder->customAttribute($key, $value);
                }
            }
        }

        return $builder->build();
    }
}
