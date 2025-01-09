<?php

declare(strict_types=1);

namespace LaunchDarkly\Subsystems;

use LaunchDarkly\Types;

/**
 * An interface for querying the status of a Big Segment store.
 *
 * The Big Segment store is the component that receives information about Big
 * Segments, normally from a database populated by the LaunchDarkly Relay
 * Proxy. Big Segments are a specific type of segments. For more information,
 * read the LaunchDarkly documentation:
 * https://docs.launchdarkly.com/home/users/big-segments
 *
 * An implementation of this interface is returned by {@see
 * LDClient::getBigSegmentStatusProvider}. Application code never needs to
 * implement this interface.
 *
 * There are two ways to interact with the status. One is to simply get the
 * current status; if its `available` property is true, then the SDK is able to
 * evaluate context membership in Big Segments, and the `stale`` property
 * indicates whether the data might be out of date.
 *
 * The other way is to subscribe to status change notifications. Applications
 * may wish to know if there is an outage in the Big Segment store, or if it
 * has become stale (the Relay Proxy has stopped updating it with new data),
 * since then flag evaluations that reference a Big Segment might return
 * incorrect values. To allow finding out about status changes as soon as
 * possible, BigSegmentStoreStatusProvider provides an observer pattern: you
 * can attach an observer that will be notified whenever the status changes.
 *
 * This listener is called inline with the status check as soon as it has been
 * determined to have changed. This included during evaluation or when the
 * status is checked directly. Listeners are encouraged to be as fast as
 * possible.
 */
interface BigSegmentStatusProvider
{
    /**
     * Gets the current status of the big segment store.
     *
     * Calling this method will trigger a query to the backing store to get the
     * current status.
     */
    public function status(): Types\BigSegmentStoreStatus;

    /**
    * Attaches a listener to be notified of status changes.
    */
    public function attach(BigSegmentStatusListener $listener): void;

    /**
     * Detaches a listener from being notified of status changes.
     */
    public function detach(BigSegmentStatusListener $listener): void;
}
