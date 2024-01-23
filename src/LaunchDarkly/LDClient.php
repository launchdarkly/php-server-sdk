<?php

declare(strict_types=1);

namespace LaunchDarkly;

use LaunchDarkly\Impl\Evaluation\EvalResult;
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
use LaunchDarkly\Migrations\OpTracker;
use LaunchDarkly\Migrations\Stage;
use LaunchDarkly\Subsystems\FeatureRequester;
use LaunchDarkly\Types\ApplicationInfo;
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
    const VERSION = '6.0.2'; // x-release-please-version

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
     * - `event_publisher`: An optional {@see \LaunchDarkly\Subsystems\EventPublisher} implementation, or a class or factory for one.
     * Defaults to {@see \LaunchDarkly\Integrations\Curl::eventPublisher()}.
     * - `all_attributes_private`: If set to true, no user attributes (other than the key) will be sent back to LaunchDarkly.
     * Defaults to false.
     * - `private_attribute_names`: An optional array of user attribute names to be marked private. Any users sent to LaunchDarkly
     * with this configuration active will have attributes with these names removed. You can also set private attributes on a
     * - `application_info`: An optional {@see \LaunchDarkly\Types\ApplicationInfo} instance.
     * per-user basis in the LDContext builder.
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

        /** @var LoggerInterface */
        $this->_logger = $options['logger'];

        $applicationInfo = $options['application_info'] ?? null;
        if ($applicationInfo instanceof ApplicationInfo) {
            foreach ($applicationInfo->errors() as $error) {
                $this->_logger->warning($error);
            }
        }

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

    public function getLogger(): LoggerInterface
    {
        return $this->_logger;
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
     * @return mixed The variation for the given context, or `$defaultValue` if the flag cannot be evaluated
     * @see \LaunchDarkly\LDClient::variationDetail()
     */
    public function variation(string $key, LDContext $context, mixed $defaultValue = false): mixed
    {
        $detail = $this->variationDetailInternal($key, $context, $defaultValue, $this->_eventFactoryDefault)['detail'];
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
     * @return EvaluationDetail An EvaluationDetail object that includes the feature flag value
     * and evaluation reason
     */
    public function variationDetail(string $key, LDContext $context, mixed $defaultValue = false): EvaluationDetail
    {
        return $this->variationDetailInternal($key, $context, $defaultValue, $this->_eventFactoryWithReasons)['detail'];
    }

    /**
     * This method returns the migration stage of the migration feature flag
     * for the given evaluation context.
     *
     * This method returns the default stage if there is an error or the flag
     * does not exist. If the default stage is not a valid stage, then a
     * default stage of {@see Stage::OFF} will be used
     * instead.
     *
     * @psalm-return array{'stage': Stage, 'tracker': OpTracker}
     */
    public function migrationVariation(string $key, LDContext $context, Stage $defaultStage): array
    {
        $result = $this->variationDetailInternal($key, $context, $defaultStage->value, $this->_eventFactoryDefault);
        /** @var EvaluationDetail $detail */
        $detail = $result['detail'];
        /** @var ?FeatureFlag $flag */
        $flag = $result['flag'];

        $value = $detail->getValue();
        $valueAsString = null;
        if (is_string($value)) {
            $valueAsString = Stage::tryFrom($detail->getValue());
        }

        if ($valueAsString !== null) {
            $tracker = new OpTracker(
                $this->_logger,
                $key,
                $flag,
                $context,
                $detail,
                $defaultStage
            );

            return ['stage' => $valueAsString, 'tracker' => $tracker];
        }

        $detail = new EvaluationDetail(
            $defaultStage->value,
            null,
            EvaluationReason::error(EvaluationReason::WRONG_TYPE_ERROR)
        );
        $tracker = new OpTracker(
            $this->_logger,
            $key,
            $flag,
            $context,
            $detail,
            $defaultStage
        );

        return ['stage' => $defaultStage, 'tracker' => $tracker];
    }

    /**
     * @param string $key
     * @param LDContext $context
     * @param mixed $default
     * @param EventFactory $eventFactory
     *
     * @psalm-return array{'detail': EvaluationDetail, 'flag': ?FeatureFlag}
     */
    private function variationDetailInternal(string $key, LDContext $context, mixed $default, EventFactory $eventFactory): array
    {
        $default = $this->_get_default($key, $default);

        $errorDetail = fn (string $errorKind): EvaluationDetail =>
            new EvaluationDetail($default, null, EvaluationReason::error($errorKind));
        $sendEvent = function (EvalResult $result, ?FeatureFlag $flag) use ($key, $context, $default, $eventFactory): void {
            if ($flag) {
                $event = $eventFactory->newEvalEvent($flag, $context, $result, $default);
            } else {
                $event = $eventFactory->newUnknownFlagEvent($key, $context, $result->getDetail());
            }
            $this->_eventProcessor->enqueue($event);
        };

        if (!$context->isValid()) {
            $result = $errorDetail(EvaluationReason::USER_NOT_SPECIFIED_ERROR);
            $sendEvent(new EvalResult($result, false), null);
            $error = $context->getError();

            $this->_logger->warning(
                "Context was invalid for flag evaluation ($error); returning default value",
                [
                    'flag' => $key,
                ]
            );

            return ['detail' => $result, 'flag' => null];
        }

        if ($this->_offline) {
            return ['detail' => $errorDetail(EvaluationReason::CLIENT_NOT_READY_ERROR), 'flag' => null];
        }

        $flag = null;
        try {
            try {
                $flag = $this->_featureRequester->getFeature($key);
            } catch (UnrecoverableHTTPStatusException $e) {
                $this->handleUnrecoverableError();
                return ['detail' => $errorDetail(EvaluationReason::EXCEPTION_ERROR), 'flag' => null];
            }

            if (is_null($flag)) {
                $result = $errorDetail(EvaluationReason::FLAG_NOT_FOUND_ERROR);
                $sendEvent(new EvalResult($result, false), null);
                return ['detail' => $result, 'flag' => null];
            }
            $evalResult = $this->_evaluator->evaluate(
                $flag,
                $context,
                function (PrerequisiteEvaluationRecord $pe) use ($context, $eventFactory) {
                    $event = $eventFactory->newEvalEvent(
                        $pe->getFlag(),
                        $context,
                        $pe->getResult(),
                        null,
                        $pe->getPrereqOfFlag()
                    );
                    $this->_eventProcessor->enqueue($event);
                }
            );
            $detail = $evalResult->getDetail();
            if ($detail->isDefaultValue()) {
                $detail = new EvaluationDetail($default, null, $detail->getReason());
                $evalResult = new EvalResult($detail, $evalResult->isForceReasonTracking());
            }
            $sendEvent($evalResult, $flag);
            return ['detail' => $detail, 'flag' => $flag];
        } catch (\Exception $e) {
            Util::logExceptionAtErrorLevel($this->_logger, $e, "Unexpected error evaluating flag $key");
            $result = $errorDetail(EvaluationReason::EXCEPTION_ERROR);
            $sendEvent(new EvalResult($result, false), null);
            return ['detail' => $result, 'flag' => $flag];
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
     * Tracks that an application-defined event occurred.
     *
     * This method creates a "custom" analytics event containing the specified event name (key)
     * and context properties. You may attach arbitrary data or a metric value to the event with the
     * optional `data` and `metricValue` parameters.
     *
     * Note that event delivery is asynchronous, so the event may not actually be sent until later;
     * see {@see \LaunchDarkly\LDClient::flush()}.
     *
     * @param string $eventName The name of the event
     * @param LDContext $context The evaluation context or user associated with the event
     * @param mixed $data Optional additional information to associate with the event
     * @param int|float|null $metricValue A numeric value used by the LaunchDarkly experimentation feature in
     *   numeric custom metrics; can be omitted if this event is used by only non-numeric metrics
     */
    public function track(string $eventName, LDContext $context, mixed $data = null, int|float|null $metricValue = null): void
    {
        if (!$context->isValid()) {
            $this->_logger->warning("Track called with null/empty user key!");
            return;
        }
        $this->_eventProcessor->enqueue($this->_eventFactoryDefault->newCustomEvent($eventName, $context, $data, $metricValue));
    }

    /**
     * Tracks the results of a migrations operation. This event includes
     * measurements which can be used to enhance the observability of a
     * migration within the LaunchDarkly UI.
     *
     * Customers making use of the {@see
     * LaunchDarkly\Migrations\MigrationBuilder} should not need to call this
     * method manually.
     *
     * Customers not using the builder should provide this method with the
     * tracker returned from calling {@see LDClient::migrationVariation}.
     */
    public function trackMigrationOperation(OpTracker $tracker): void
    {
        $event = $tracker->build();

        if (is_string($event)) {
            $this->_logger->error("error generating migration op event {$event}; no event will be emitted");
            return;
        }

        $this->_eventProcessor->enqueue($event);
    }

    /**
     * Reports details about an evaluation context.
     *
     * This method simply creates an analytics event containing the context properties, to
     * that LaunchDarkly will know about that context if it does not already.
     *
     * Evaluating a flag, by calling {@see \LaunchDarkly\LDClient::variation()} or
     * {@see \LaunchDarkly\LDClient::variationDetail()} :func:`variation_detail()`, also sends
     * the context information to LaunchDarkly (if events are enabled), so you only need to use
     * identify() if you want to identify the context without evaluating a flag.
     *
     * @param LDContext $context The context to register
     * @return void
     */
    public function identify(LDContext $context): void
    {
        if (!$context->isValid()) {
            $this->_logger->warning("Identify called with null/empty user key!");
            return;
        }
        $this->_eventProcessor->enqueue($this->_eventFactoryDefault->newIdentifyEvent($context));
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
     * @param LDContext $context The evaluation context
     * @return string The hash value
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
