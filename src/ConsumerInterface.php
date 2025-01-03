<?php

namespace Nimbly\Syndicate;

interface ConsumerInterface
{
	/**
	 * Grab some messages.
	 *
	 * @param string $topic The topic or queue name to consume messages from.
	 * @param int $max_messages Maxiumum number of messages to retrieve.
	 * @param array<string,mixed> Implementation specific options.
	 * @return array<Message>
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array;

	/**
	 * Acknowledge message.
	 *
	 * @param Message $message
	 * @return void
	 */
	public function ack(Message $message): void;

	/**
	 * Disavow or release message.
	 *
	 * @param Message $message
	 * @param integer $timeout
	 * @return void
	 */
	public function nack(Message $message, int $timeout = 0): void;
}