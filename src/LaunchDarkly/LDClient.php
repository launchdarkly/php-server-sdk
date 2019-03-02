<?php
namespace LaunchDarkly;

use LaunchDarkly\Impl\EventFactory;
use LaunchDarkly\Integrations\Guzzle;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * A client for the LaunchDarkly API.
 */
class LDClient
{
    const DEFAULT_BASE_URI = 'https://app.launchdarkly.com';
    const DEFAULT_EVENTS_URI = 'https://events.launchdarkly.com';
    const VERSION = '3.5.0';

    /** @var string */
    protected $_sdkKey;
    /** @var string */
    protected $_baseUri;
    /** @var string */
    protected $_eventsUri;
    /** @var EventProcessor */
    protected $_eventProcessor;
    /** @var  bool */
    protected $_offline = false;
    /** @var bool */
    protected $_send_events = true;
    /** @var array|mixed */
    protected $_defaults = array();
    /** @var LoggerInterface */
    protected $_logger;
    /** @var FeatureRequester */
    protected $_featureRequester;
    /** @var EventFactory */
    protected $_eventFactoryDefault;
    /** @var EventFactory */
    protected $_eventFactoryWithReasons;

    /**
     * Creates a new client instance that connects to LaunchDarkly.
     *
     * @param string $sdkKey The SDK key for your account
     * @param array $options Client configuration settings
     *     - base_uri: Base URI of the LaunchDarkly API. Defaults to `https://app.launchdarkly.com`.
     *     - events_uri: Base URI for sending events to LaunchDarkly. Defaults to 'https://events.launchdarkly.com'
     *     - timeout: Float describing the maximum length of a request in seconds. Defaults to 3
     *     - connect_timeout: Float describing the number of seconds to wait while trying to connect to a server. Defaults to 3
     *     - cache: An optional Kevinrob\GuzzleCache\Storage\CacheStorageInterface. Defaults to an in-memory cache.
     *     - send_events: An optional bool that can disable the sending of events to LaunchDarkly. Defaults to true.
     *     - logger: An optional Psr\Log\LoggerInterface. Defaults to a Monolog\Logger sending all messages to the php error_log.
     *     - offline: An optional boolean which will disable all network calls and always return the default value. Defaults to false.
     *     - feature_requester: An optional LaunchDarkly\FeatureRequester instance, or a class or factory for one. Defaults to {@link \LaunchDarkly\Integrations\Guzzle::featureRequester()}.
     *     - feature_requester_class: Deprecated, equivalent to `feature_requester`.
     *     - event_publisher: An optional LaunchDarkly\EventPublisher instance, or a class or factory for one. Defaults to {@link \LaunchDarkly\Integrations\Curl::eventPublisher()}.
     *     - event_publisher_class: Deprecated, equivalent to `event_publisher`.
     *     - all_attributes_private: True if no user attributes (other than the key) should be sent back to LaunchDarkly. By default, this is false.
     *     - private_attribute_names: An optional array of user attribute names to be marked private. Any users sent to LaunchDarkly with this configuration active will have attributes with these names removed. You can also set private attributes on a per-user basis in LDUserBuilder.
     *     - Other options may be available depending on which features you are using from {@link \LaunchDarkly\Integrations}.
     * By default, those are {@link \LaunchDarkly\Integrations\Guzzle::featureRequester()} and
     * {@link \LaunchDarkly\Integrations\Curl::eventPublisher()}.
     */
    public function __construct($sdkKey, $options = array())
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

        $this->_eventProcessor = new EventProcessor($sdkKey, $options);

        $this->_featureRequester = $this->getFeatureRequester($sdkKey, $options);
    }

    /**
     * @param string $sdkKey
     * @param mixed[] $options
     * @return FeatureRequester
     */
    private function getFeatureRequester($sdkKey, array $options)
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
        if (is_a($fr, FeatureRequester::class, true)) {
            return new $fr($this->_baseUri, $sdkKey, $options);
        }
        throw new \InvalidArgumentException('invalid feature_requester');
    }

    /**
     * Calculates the value of a feature flag for a given user.
     *
     * @param string $key The unique key for the feature flag
     * @param LDUser $user The end user requesting the flag
     * @param mixed $default The default value of the flag
     *
     * @return mixed The result of the Feature Flag evaluation, or $default if any errors occurred.
     */
    public function variation($key, $user, $default = false)
    {
        $detail = $this->variationDetailInternal($key, $user, $default, $this->_eventFactoryDefault);
        return $detail->getValue();
    }

    /**
     * Calculates the value of a feature flag, and returns an object that describes the way the
     * value was determined. The "reason" property in the result will also be included in
     * analytics events, if you are capturing detailed event data for this flag.
     *
     * @param string $key The unique key for the feature flag
     * @param LDUser $user The end user requesting the flag
     * @param mixed $default The default value of the flag
     *
     * @return EvaluationDetail An EvaluationDetail object that includes the feature flag value
     * and evaluation reason.
     */
    public function variationDetail($key, $user, $default = false)
    {
        return $this->variationDetailInternal($key, $user, $default, $this->_eventFactoryWithReasons);
    }

    /**
     * @param string $key
     * @param LDUser $user
     * @param mixed $default
     * @param EventFactory $eventFactory
     */
    private function variationDetailInternal($key, $user, $default, $eventFactory)
    {
        $default = $this->_get_default($key, $default);

        $errorResult = function ($errorKind) use ($key, $default) {
            return new EvaluationDetail($default, null, EvaluationReason::error($errorKind));
        };
        $sendEvent = function ($detail, $flag) use ($key, $user, $default, $eventFactory) {
            if ($this->isOffline() || !$this->_send_events) {
                return;
            }
            if ($flag) {
                $event = $eventFactory->newEvalEvent($flag, $user, $detail, $default);
            } else {
                $event = $eventFactory->newUnknownFlagEvent($key, $user, $detail);
            }
            $this->_eventProcessor->enqueue($event);
        };

        if ($this->_offline) {
            return $errorResult(EvaluationReason::CLIENT_NOT_READY_ERROR);
        }

        try {
            if (!is_null($user) && $user->isKeyBlank()) {
                $this->_logger->warning("User key is blank. Flag evaluation will proceed, but the user will not be stored in LaunchDarkly.");
            }
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
            if (is_null($user) || is_null($user->getKey())) {
                $result = $errorResult(EvaluationReason::USER_NOT_SPECIFIED_ERROR);
                $sendEvent($result, $flag);
                $this->_logger->warning("Variation called with null user or null user key! Returning default value");
                return $result;
            }
            $evalResult = $flag->evaluate($user, $this->_featureRequester, $eventFactory);
            if (!$this->isOffline() && $this->_send_events) {
                foreach ($evalResult->getPrerequisiteEvents() as $e) {
                    $this->_eventProcessor->enqueue($e);
                }
            }
            $detail = $evalResult->getDetail();
            if ($detail->isDefaultValue()) {
                $detail = new EvaluationDetail($default, null, $detail->getReason());
            }
            $sendEvent($detail, $flag);
            return $detail;
        } catch (\Exception $e) {
            $this->_logger->error("Caught $e");
            $result = $errorResult(EvaluationReason::EXCEPTION_ERROR);
            try {
                $sendEvent($result, null);
            } catch (\Exception $e) {
                $this->_logger->error("Caught $e");
            }
            return $result;
        }
    }

    /** @deprecated Use variation() instead.
     * @param $key
     * @param $user
     * @param bool $default
     * @return mixed
     */
    public function toggle($key, $user, $default = false)
    {
        $this->_logger->warning("Deprecated function: toggle() called. Use variation() instead.");
        return $this->variation($key, $user, $default);
    }

    /**
     * Returns whether the LaunchDarkly client is in offline mode.
     *
     */
    public function isOffline()
    {
        return $this->_offline;
    }

    /**
     * Tracks that a user performed an event.
     *
     * @param $eventName string The name of the event
     * @param $user LDUser The user that performed the event
     * @param $data mixed
     */
    public function track($eventName, $user, $data)
    {
        if ($this->isOffline()) {
            return;
        }
        if (is_null($user) || $user->isKeyBlank()) {
            $this->_logger->warning("Track called with null user or null/empty user key!");
        }
        $this->_eventProcessor->enqueue($this->_eventFactoryDefault->newCustomEvent($eventName, $user, $data));
    }

    /**
     * @param $user LDUser
     */
    public function identify($user)
    {
        if ($this->isOffline()) {
            return;
        }
        if (is_null($user) || $user->isKeyBlank()) {
            $this->_logger->warning("Track called with null user or null/empty user key!");
        }
        $this->_eventProcessor->enqueue($this->_eventFactoryDefault->newIdentifyEvent($user));
    }

    /** Returns an array mapping Feature Flag keys to their evaluated results for a given user.
     *
     * If the result of a flag's evaluation would have returned the default variation, it will have a null entry.
     * If the client is offline, has not been initialized, or a null user or user with null/empty user key, null will be returned.
     * This method will not send analytics events back to LaunchDarkly.
     * <p>
     * The most common use case for this method is to bootstrap a set of client-side feature flags from a back-end service.
     * @deprecated Use allFlagsState() instead. Current versions of the client-side SDK will not
     * generate analytics events correctly if you pass the result of allFlags().
     * @param $user LDUser the end user requesting the feature flags
     * @return array()|null Mapping of feature flag keys to their evaluated results for $user
     */
    public function allFlags($user)
    {
        $state = $this->allFlagsState($user);
        if (!$state->isValid()) {
            return null;
        }
        return $state->toValuesMap();
    }

    /**
     * Returns an object that encapsulates the state of all feature flags for a given user, including the flag
     * values and also metadata that can be used on the front end. This method does not send analytics events
     * back to LaunchDarkly.
     * <p>
     * The most common use case for this method is to bootstrap a set of client-side feature flags from a back-end service.
     * To convert the state object into a JSON data structure, call its toJson() method.
     * @param $user LDUser the end user requesting the feature flags
     * @param $options array optional properties affecting how the state is computed; set
     *   <code>'clientSideOnly' => true</code> to specify that only flags marked for client-side use
     *   should be included (by default, all flags are included); set <code>'withReasons' => true</code>
     *   to include evaluation reasons (see <code>variationDetail</code>)
     * @return FeatureFlagsState a FeatureFlagsState object (will never be null; see FeatureFlagsState.isValid())
     */
    public function allFlagsState($user, $options = array())
    {
        if (is_null($user) || is_null($user->getKey())) {
            $this->_logger->warn("allFlagsState called with null user or null/empty user key! Returning empty state");
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

        $state = new FeatureFlagsState(true);
        $clientOnly = isset($options['clientSideOnly']) && $options['clientSideOnly'];
        $withReasons = isset($options['withReasons']) && $options['withReasons'];
        $detailsOnlyIfTracked = isset($options['detailsOnlyForTrackedFlags']) && $options['detailsOnlyForTrackedFlags'];
        foreach ($flags as $key => $flag) {
            if ($clientOnly && !$flag->isClientSide()) {
                continue;
            }
            $result = $flag->evaluate($user, $preloadedRequester, $this->_eventFactoryDefault);
            $state->addFlag($flag, $result->getDetail(), $withReasons, $detailsOnlyIfTracked);
        }
        return $state;
    }

    /** Generates an HMAC sha256 hash for use in Secure mode: https://github.com/launchdarkly/js-client#secure-mode
     * @param $user LDUser
     * @return string
     */
    public function secureModeHash($user)
    {
        if (is_null($user) || strlen($user->getKey()) === 0) {
            return "";
        }
        return hash_hmac("sha256", $user->getKey(), $this->_sdkKey, false);
    }

    /**
     * Publish events to LaunchDarkly
     * @return bool Whether the events were successfully published
     */
    public function flush()
    {
        try {
            return $this->_eventProcessor->flush();
        } catch (UnrecoverableHTTPStatusException $e) {
            $this->handleUnrecoverableError();
        }
    }

    protected function _get_default($key, $default)
    {
        if (array_key_exists($key, $this->_defaults)) {
            return $this->_defaults[$key];
        } else {
            return $default;
        }
    }

    protected function handleUnrecoverableError()
    {
        $this->_logger->error("Due to an unrecoverable HTTP error, no further HTTP requests will be made during lifetime of LDClient");
        $this->_offline = true;
    }
}
