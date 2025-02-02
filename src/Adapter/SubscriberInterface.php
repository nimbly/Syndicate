<?php

namespace Nimbly\Syndicate\Adapter;

use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\SubscriptionException;

/**
 * Subscribers are integrations that require subscriptions to be declared: a topic name and a callback.
 */
interface SubscriberInterface
{
	/**
	 * Subscribe a topic(s) to a callback.
	 *
	 * @param string|array<string> $topics A topic name or an array of topic names to subscribe to.
	 * @param callable $callback The callback function to trigger when a message from topic is received.
	 * @param array<string,mixed> $options Key/value pairs of options. This is dependent on the implementation being used.
	 * @throws ConnectionException
	 * @throws SubscriptionException
	 * @return void
	 */
	public function subscribe(string|array $topics, callable $callback, array $options = []): void;

	/**
	 * Begin consumer loop.
	 *
	 * @param array<string,mixed> $options Key/value pairs of options. This is dependent on the implementation being used.
	 * @throws ConnectionException
	 * @throws ConsumeException
	 * @return void
	 */
	public function loop(array $options = []): void;

	/**
	 * Shutdown the consumer loop.
	 *
	 * @throws ConnectionException
	 * @return void
	 */
	public function shutdown(): void;
}