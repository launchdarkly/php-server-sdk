<?php

declare(strict_types=1);

namespace LaunchDarkly;

use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Evaluation\PrerequisiteEvaluationRecord;
use LaunchDarkly\Impl\Events\EventFactory;
use LaunchDarkly\Impl\Events\EventProcessor;
use LaunchDarkly\Impl\Events\NullEventProcessor;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\PreloadedFeatureRequester;
use LaunchDarkly\Impl\UnrecoverableHTTPStatusException;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\Integrations\Guzzle;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * A client for the LaunchDarkly API.
 */
class LDClient
{
    /** @var string */
    const DEFAULT_BASE_URI = 'https://sdk.launchdarkly.com';
    /** @var string */
    const DEFAULT_EVENTS_URI = 'https://events.launchdarkly.com';
    /**
     * The current SDK version.
     * @var string
     */
    const VERSION = '4.2.4';

    protected string $_sdkKey;
    protected string $_baseUri;
    protected string $_eventsUri;
    protected Evaluator $_evaluator;
    protected EventProcessor $_eventProcessor;
    protected bool $_offline = false;
    protected bool $_send_events = true;
    protected array $_defaults = [];
    protected LoggerInterface $_logger;
    protected FeatureRequester $_featureRequester;
    protected EventFactory $_eventFactoryDefault;
    protected EventFactory $_eventFactoryWithReasons;

    /**
     * Creates a new client instance that connects to LaunchDarkly.
     *
     * @psalm-param array{capacity?: int, defaults?: array<string, mixed|null>} $options
     *
     * @param string $sdkKey The SDK key for your account
     * @param array $options Client configuration settings
     * - `base_uri`: Base URI of the LaunchDarkly service. Change this if you are connecting to a Relay Proxy instance instead of
     * directly to LaunchDarkly. To learn more, read [The Relay Proxy](https://docs.launchdarkly.com/home/relay-proxy).
     * - `events_uri`: Base URI for sending events to LaunchDarkly. Change this if you are forwarding events through a Relay Proxy instance.
     * - `timeout`: The maximum length of an HTTP request in seconds. Defaults to 3.
     * - `connect_timeout`: The maximum number of seconds to wait while trying to connect to a server. Defaults to 3.
     * - `cache`: An optional implementation of Guzzle's [CacheStorageInterface](https://github.com/Kevinrob/guzzle-cache-middleware/blob/master/src/Storage/CacheStorageInterface.php).
     * Defaults to an in-memory cache.
     * - `send_events`: If set to false, disables the sending of events to LaunchDarkly. Defaults to true.
     * - `logger`: An optional implementation of [Psr\Log\LoggerInterface](https://www.php-fig.org/psr/psr-3/). Defaults to a
     * Monolog\Logger sending all messages to the PHP error_log.
     * - `offline`: If set to true, disables all network calls and always returns the default value for flags. Defaults to false.
     * - `feature_requester`: An optional {@see \LaunchDarkly\FeatureRequester} implementation, or a class or factory for one.
     * Defaults to {@see \LaunchDarkly\Integrations\Guzzle::featureRequester()}. There are also optional packages providing
     * database integrations; see [Storing data](https://docs.launchdarkly.com/sdk/features/storing-data#php).
     * - `feature_requester_class`: Deprecated, equivalent to feature_requester.
     * - `event_publisher`: An optional {@see \LaunchDarkly\EventPublisher} implementation, or a class or factory for one.
     * Defaults to {@see \LaunchDarkly\Integrations\Curl::eventPublisher()}.
     * - `event_publisher_class`: Deprecated, equivalent to event_publisher.
     * - `all_attributes_private`: If set to true, no user attributes (other than the key) will be sent back to LaunchDarkly.
     * Defaults to false.
     * - `private_attribute_names`: An optional array of user attribute names to be marked private. Any users sent to LaunchDarkly
     * with this configuration active will have attributes with these names removed. You can also set private attributes on a
     * per-user basis in LDUserBuilder.
     * - Other options may be available depending on any features you are using from the `LaunchDarkly\Integrations` namespace.
     *
     * @return LDClient
     */
    public function __construct(string $sdkKey, array $options = [])
    {
        $this->_sdkKey = $sdkKey;
        if (!isset($options['base_uri'])) {
            $this->_baseUri = self::DEFAULT_BASE_URI;
        } else {
            $this->_baseUri = rtrim($options['base_uri'], '/');
        }
        if (!isset($options['events_uri'])) {
            $this->_eventsUri = self::DEFAULT_EVENTS_URI;
        } else {
            $this->_eventsUri = rtrim($options['events_uri'], '/');
        }
        if (isset($options['send_events'])) {
            $this->_send_events = $options['send_events'];
        }
        if (isset($options['offline']) && $options['offline'] === true) {
            $this->_offline = true;
            $this->_send_events = false;
        }

        if (isset($options['defaults'])) {
            $this->_defaults = $options['defaults'];
        }

        if (!isset($options['timeout'])) {
            $options['timeout'] = 3;
        }
        if (!isset($options['connect_timeout'])) {
            $options['connect_timeout'] = 3;
        }

        if (!isset($options['capacity'])) {
            $options['capacity'] = 1000;
        }

        if (!isset($options['logger'])) {
            $logger = new Logger("LaunchDarkly", [new ErrorLogHandler()]);
            $options['logger'] = $logger;
        }
        $this->_logger = $options['logger'];

        $this->_eventFactoryDefault = new EventFactory(false);
        $this->_eventFactoryWithReasons = new EventFactory(true);

        if (isset($options['event_processor'])) {
            $ep = $options['event_processor'];
            if (is_callable($ep)) {
                $ep = $ep($sdkKey, $options);
            }
            $this->_eventProcessor = $ep;
        } elseif ($this->_offline || !$this->_send_events) {
            $this->_eventProcessor = new NullEventProcessor($sdkKey, $options);
        } else {
            $this->_eventProcessor = new EventProcessor($sdkKey, $options);
        }

        $this->_featureRequester = $this->getFeatureRequester($sdkKey, $options);

        $this->_evaluator = new Evaluator($this->_featureRequester, $this->_logger);
    }

    /**
     * @param string $sdkKey
     * @param mixed[] $options
     * @return FeatureRequester
     *
     * @psalm-suppress UndefinedClass
     */
    private function getFeatureRequester(string $sdkKey, array $options): FeatureRequester
    {
        if (isset($options['feature_requester']) && $options['feature_requester']) {
            $fr = $options['feature_requester'];
        } elseif (isset($options['feature_requester_class']) && $options['feature_requester_class']) {
            $fr = $options['feature_requester_class'];
        } else {
            $fr = Guzzle::featureRequester();
        }
        if ($fr instanceof FeatureRequester) {
            return $fr;
        }
        if (is_callable($fr)) {
            return $fr($this->_baseUri, $sdkKey, $options);
        }
        /**
         * @psalm-suppress LessSpecificReturnStatement
         */
        if (is_a($fr, FeatureRequester::class, true)) {
            return new $fr($this->_baseUri, $sdkKey, $options);
        }
        throw new \InvalidArgumentException('invalid feature_requester');
    }

    /**
     * Calculates the value of a feature flag for a given context.
     *
     * If an error makes it impossible to evaluate the flag (for instance, the feature flag key
     * does not match any existing flag), `$defaultValue` is returned.
     *
     * @param string $key the unique key for the feature flag
     * @param LDContext $context the evaluation context
     * @param mixed $defaultValue the default value of the flag
     * @return mixed the variation for the given context, or `$defaultValue` if the flag cannot be evaluated
     * @see \LaunchDarkly\LDClient::variationDetail()
     */
    public function variation(string $key, LDContext $context, mixed $defaultValue = false): mixed
    {
        $detail = $this->variationDetailInternal($key, $context, $defaultValue, $this->_eventFactoryDefault);
        return $detail->getValue();
    }

    /**
     * Calculates the value of a feature flag for a given context, and returns an object that
     * describes the way the value was determined.
     *
     * The "reason" property in the result will also be included in analytics events, if you are capturing
     * detailed event data for this flag.
     *
     * @param string $key the unique key for the feature flag
     * @param LDContext $context the evaluation context
     * @param mixed $defaultValue the default value of the flag
     *
     * @return EvaluationDetail an EvaluationDetail object that includes the feature flag value
     * and evaluation reason
     */
    public function variationDetail(string $key, LDContext $context, mixed $defaultValue = false): EvaluationDetail
    {
        return $this->variationDetailInternal($key, $context, $defaultValue, $this->_eventFactoryWithReasons);
    }

    /**
     * @param string $key
     * @param LDContext $context
     * @param mixed $default
     * @param EventFactory $eventFactory
     *
     * @return EvaluationDetail
     */
    private function variationDetailInternal(string $key, LDContext $context, mixed $default, EventFactory $eventFactory): EvaluationDetail
    {
        $default = $this->_get_default($key, $default);

        $errorResult = fn (string $errorKind): EvaluationDetail =>
            new EvaluationDetail($default, null, EvaluationReason::error($errorKind));
        $sendEvent = function (EvaluationDetail $detail, ?FeatureFlag $flag) use ($key, $context, $default, $eventFactory): void {
            // if ($flag) {
            //     $event = $eventFactory->newEvalEvent($flag, $context, $detail, $default);
            // } else {
            //     $event = $eventFactory->newUnknownFlagEvent($key, $context, $detail);
            // }
            // $this->_eventProcessor->enqueue($event);
        };

        if (!$context->isValid()) {
            $result = $errorResult(EvaluationReason::USER_NOT_SPECIFIED_ERROR);
            $sendEvent($result, null);
            $error = $context->getError();
            $this->_logger->warning("Context was invalid for flag evaluation ($error); returning default value");
            return $result;
        }

        if ($this->_offline) {
            return $errorResult(EvaluationReason::CLIENT_NOT_READY_ERROR);
        }

        try {
            try {
                $flag = $this->_featureRequester->getFeature($key);
            } catch (UnrecoverableHTTPStatusException $e) {
                $this->handleUnrecoverableError();
                return $errorResult(EvaluationReason::EXCEPTION_ERROR);
            }

            if (is_null($flag)) {
                $result = $errorResult(EvaluationReason::FLAG_NOT_FOUND_ERROR);
                $sendEvent($result, null);
                return $result;
            }
            $evalResult = $this->_evaluator->evaluate(
                $flag,
                $context,
                function (PrerequisiteEvaluationRecord $pe) {
                    // TODO: events temporarily disabled till they support contexts
                    // $event = $eventFactory->newEvalEvent(
                    //     $pe->getFlag(),
                    //     $context,
                    //     $pe->getResult(),
                    //     null,
                    //     $pe->getPrereqOfFlag()
                    // );
                    // $this->_eventProcessor->enqueue($event);
                }
            );
            $detail = $evalResult->getDetail();
            if ($detail->isDefaultValue()) {
                $detail = new EvaluationDetail($default, null, $detail->getReason());
            }
            $sendEvent($detail, $flag);
            return $detail;
        } catch (\Exception $e) {
            Util::logExceptionAtErrorLevel($this->_logger, $e, "Unexpected error evaluating flag $key");
            $result = $errorResult(EvaluationReason::EXCEPTION_ERROR);
            $sendEvent($result, null);
            return $result;
        }
    }

    /**
     * Returns whether the LaunchDarkly client is in offline mode.
     */
    public function isOffline(): bool
    {
        return $this->_offline;
    }

    /**
     * Tracks that a user performed an event.
     *
     * @param string $eventName The name of the event
     * @param LDUser $user The user that performed the event
     * @param mixed $data Optional additional information to associate with the event
     * @param int|float|null $metricValue A numeric value used by the LaunchDarkly experimentation feature in
     *   numeric custom metrics. Can be omitted if this event is used by only non-numeric metrics. This
     *   field will also be returned as part of the custom event for Data Export.
     */
    public function track(string $eventName, LDUser $user, mixed $data = null, int|float|null $metricValue = null): void
    {
        if ($user->isKeyBlank()) {
            $this->_logger->warning("Track called with null/empty user key!");
            return;
        }
        $this->_eventProcessor->enqueue($this->_eventFactoryDefault->newCustomEvent($eventName, $user, $data, $metricValue));
    }

    /**
     * Reports details about a user.
     *
     * This simply registers the given user properties with LaunchDarkly without evaluating a feature flag.
     * This also happens automatically when you evaluate a flag.
     *
     * @param LDUser $user The user properties
     * @return void
     */
    public function identify(LDUser $user): void
    {
        if ($user->isKeyBlank()) {
            $this->_logger->warning("Identify called with null/empty user key!");
            return;
        }
        $this->_eventProcessor->enqueue($this->_eventFactoryDefault->newIdentifyEvent($user));
    }

    /**
     * Returns an object that encapsulates the state of all feature flags for a given context.
     *
     * This includes the flag values as well as other flag metadata that may be needed by front-end code,
     * since the most common use case for this method is [bootstrapping](https://docs.launchdarkly.com/sdk/features/bootstrapping)
     * in conjunction with the JavaScript browser SDK.
     *
     * This method does not send analytics events back to LaunchDarkly.
     *
     * @param LDContext $context the evalation context
     * @param array $options Optional properties affecting how the state is computed:
     * - `clientSideOnly`: Set this to true to specify that only flags marked for client-side use
     * should be included; by default, all flags are included
     * - `withReasons`: Set this to true to include evaluation reasons (see {@see \LaunchDarkly\LDClient::variationDetail()})
     * - `detailsOnlyForTrackedFlags`: Set to true to omit any metadata that is
     * normally only used for event generation, such as flag versions and
     * evaluation reasons, unless the flag has event tracking or debugging
     * turned on
     *
     * @return FeatureFlagsState a FeatureFlagsState object (will never be null)
     */
    public function allFlagsState(LDContext $context, array $options = []): FeatureFlagsState
    {
        if (!$context->isValid()) {
            $error = $context->getError();
            $this->_logger->warning("Invalid context for allFlagsState ($error); returning empty state");
            return new FeatureFlagsState(false);
        }

        if ($this->isOffline()) {
            return new FeatureFlagsState(false);
        }
        try {
            $flags = $this->_featureRequester->getAllFeatures();
        } catch (UnrecoverableHTTPStatusException $e) {
            $this->handleUnrecoverableError();
            return new FeatureFlagsState(false);
        }
        if ($flags === null) {
            return new FeatureFlagsState(false);
        }

        $preloadedRequester = new PreloadedFeatureRequester($this->_featureRequester, $flags);
        // This saves us from doing repeated queries for prerequisite flags during evaluation
        $tempEvaluator = new Evaluator($preloadedRequester);

        $state = new FeatureFlagsState(true);
        $clientOnly = !!($options['clientSideOnly'] ?? false);
        $withReasons = !!($options['withReasons'] ?? false);
        $detailsOnlyIfTracked = !!($options['detailsOnlyForTrackedFlags'] ?? false);
        foreach ($flags as $flag) {
            if ($clientOnly && !$flag->isClientSide()) {
                continue;
            }
            $result = $tempEvaluator->evaluate($flag, $context, null);
            $state->addFlag($flag, $result->getDetail(), $result->isForceReasonTracking(), $withReasons, $detailsOnlyIfTracked);
        }
        return $state;
    }

    /**
     * Generates an HMAC sha256 hash for use in Secure mode.
     *
     * See: [Secure mode](https://docs.launchdarkly.com/sdk/features/secure-mode)
     *
     * @param LDContext $context the evaluation context
     * @return string the hash value
     */
    public function secureModeHash(LDContext $context): string
    {
        if (!$context->isValid()) {
            return "";
        }
        return hash_hmac("sha256", $context->getFullyQualifiedKey(), $this->_sdkKey, false);
    }

    /**
     * Publishes any pending analytics events to LaunchDarkly.
     *
     * This is normally done automatically by the SDK.
     * @return bool Whether the events were successfully published
     */
    public function flush(): bool
    {
        try {
            return $this->_eventProcessor->flush();
        } catch (UnrecoverableHTTPStatusException $e) {
            $this->handleUnrecoverableError();
            return false;
        }
    }

    protected function _get_default(string $key, mixed $default): mixed
    {
        if (array_key_exists($key, $this->_defaults)) {
            return $this->_defaults[$key];
        } else {
            return $default;
        }
    }

    protected function handleUnrecoverableError(): void
    {
        $this->_logger->error("Due to an unrecoverable HTTP error, no further HTTP requests will be made during lifetime of LDClient");
        $this->_offline = true;
    }
}
