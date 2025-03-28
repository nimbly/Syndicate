<?php

namespace Nimbly\Syndicate\Adapter;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\ConnectionException;

/**
 * A consumer is any adapter that can retrieve messages to be
 * processed.
 */
interface ConsumerInterface
{
	/**
	 * Grab a message or messages from the given topic.
	 *
	 * @param string $topic The topic or queue name/URL to consume messages from.
	 * @param int $max_messages Maxiumum number of messages to retrieve at once.
	 * @param array<string,mixed> Implementation specific options.
	 * @throws ConnectionException
	 * @throws ConsumeException
	 * @return array<Message>
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array;

	/**
	 * Acknowledge message.
	 *
	 * @param Message $message
	 * @throws ConnectionException
	 * @throws ConsumeException
	 * @return void
	 */
	public function ack(Message $message): void;

	/**
	 * Disavow or release message.
	 *
	 * @param Message $message
	 * @param integer $timeout
	 * @throws ConnectionException
	 * @throws ConsumeException
	 * @return void
	 */
	public function nack(Message $message, int $timeout = 0): void;
}