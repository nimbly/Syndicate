<?php

namespace Nimbly\Syndicate;

interface LoopConsumerInterface
{
	/**
	 * Subscribe a topic(s) to a callback.
	 *
	 * @param string|array<string> $topic
	 * @param string|callable $callback
	 * @param array<string,mixed> $options
	 * @throws ConnectionException
	 * @throws ConsumerException
	 * @return void
	 */
	public function subscribe(string|array $topic, string|callable $callback, array $options = []): void;

	/**
	 * Begin consumer loop.
	 *
	 * @param array<string,mixed> $options
	 * @throws ConnectionException
	 * @throws ConsumerException
	 * @return void
	 */
	public function loop(array $options = []): void;

	/**
	 * Shutdown the consumer loop.
	 *
	 * @return void
	 */
	public function shutdown(): void;
}