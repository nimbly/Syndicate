<?php

namespace Nimbly\Syndicate\Queue;

use IronMQ\IronMQ;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Throwable;

class Iron implements PublisherInterface, ConsumerInterface
{
	/**
	 * @param IronMQ $client
	 */
	public function __construct(
		protected IronMQ $client)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$result = $this->client->postMessage(
				$message->getTopic(),
				$message->getPayload(),
				$options
			);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	`timeout` (integer)
	 *  `wait` (integer)
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		try {

			$reservedMessages = $this->client->reserveMessages(
				queue_name: $topic,
				count: $max_messages,
				timeout: $options["timeout"] ?? IronMQ::GET_MESSAGE_TIMEOUT,
				wait: $options["wait"] ?? IronMQ::GET_MESSAGE_WAIT
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		return \array_map(
			function(object $reservedMessage) use ($topic): Message {
				return new Message(
					topic: $topic,
					payload: $reservedMessage->body,
					reference: [$reservedMessage->id, $reservedMessage->reservation_id]
				);
			},
			$reservedMessages ?? []
		);
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		[$message_id, $reservation_id] = $message->getReference();

		try {

			$this->client->deleteMessage(
				$message->getTopic(),
				$message_id,
				$reservation_id,
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to ack message.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function nack(Message $message, int $timeout = 0): void
	{
		[$message_id, $reservation_id] = $message->getReference();

		try {

			$this->client->releaseMessage(
				$message->getTopic(),
				$message_id,
				$reservation_id,
				$timeout
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}