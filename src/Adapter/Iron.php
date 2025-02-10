<?php

namespace Nimbly\Syndicate\Adapter;

use IronCore\HttpException;
use IronMQ\IronMQ;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
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
	 *
	 * Message attributes:
	 * * `expires_in` (integer, optional, default `604800`) Amount of time, in seconds, when message expires and will be automatically deleted.
	 *
	 * Options:
	 * * `delay` (integer, optional, default `0`) Amount of time (in seconds) before message becomes available for consuming.
	 * * `timeout` (integer, optional, default `60`) Amount of time (in seconds) a reserved message is automatically released.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$properties = \array_filter([
			"delay" => $options["delay"] ?? null,
			"timeout" => $options["timeout"] ?? null,
			"expires_in" => $message->getAttributes()["expires_in"] ?? null
		]);

		try {

			$result = $this->client->postMessage(
				$message->getTopic(),
				$message->getPayload(),
				$properties
			);
		}
		catch( HttpException $exception ){
			throw new ConnectionException(
				message: "Connection to IronMQ failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new PublishException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return (string) $result->id;
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
		catch( HttpException $exception ){
			throw new ConnectionException(
				message: "Connection to IronMQ failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumeException(
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
		catch( HttpException $exception ){
			throw new ConnectionException(
				message: "Connection to IronMQ failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumeException(
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
		catch( HttpException $exception ){
			throw new ConnectionException(
				message: "Connection to IronMQ failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumeException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}