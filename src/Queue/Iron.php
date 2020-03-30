<?php

namespace Syndicate\Queue;

use IronMQ\IronMQ;
use Syndicate\Message;

/**
 *
 * @property IronMQ $client
 *
 */
class Iron extends Queue
{
	/**
	 * Iron Message Queue constructor.
	 *
	 * @param string $name
	 * @param IronMQ $client
	 */
	public function __construct(string $name, IronMQ $client)
	{
		$this->name = $name;
		$this->client = $client;
	}

	/**
	 * @inheritDoc
	 */
	public function put($data, array $options = []): void
	{
		$this->client->postMessage(
			$this->name,
			$this->serialize($data),
			$options
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get(array $options = []): ?Message
	{
		$messages = $this->many(1, $options);
		return $messages[0] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function many(int $max, array $options = []): array
	{
		$reservedMessages = $this->client->reserveMessages(
			$this->name,
			$max,
			$options['timeout'] ?? IronMQ::GET_MESSAGE_TIMEOUT,
			$options['wait'] ?? IronMQ::GET_MESSAGE_WAIT
		);

		return \array_map(
			function(object $reservedMessage): Message {

				return new Message($this, $reservedMessage, $this->deserialize($reservedMessage->body));

			},
			$reservedMessages ?? []
		);
	}

	/**
	 * @inheritDoc
	 */
	public function release(Message $message, array $options = []): void
	{
		$reservedMessage = $message->getSourceMessage();

		$this->client->releaseMessage(
			$this->name,
			$reservedMessage->id,
			$reservedMessage->reservation_id,
			$options['delay'] ?? 0
		);
	}

	/**
	 * @inheritDoc
	 */
	public function delete(Message $message): void
	{
		$reservedMessage = $message->getSourceMessage();

		$this->client->deleteMessage(
			$this->name,
			$reservedMessage->id,
			$reservedMessage->reservation_id
		);
	}
}