<?php

namespace Nimbly\Syndicate\Queue;

use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Predis\Client;
use Throwable;

class Redis implements PublisherInterface, ConsumerInterface
{
	/**
	 * @param Client $client
	 */
	public function __construct(
		protected Client $client)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$result = $this->client->rpush(
				$message->getTopic(),
				[$message->getPayload()]
			);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return (string) $result;
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	None
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		try {

			/**
			 * @var array<string> $messages
			 */
			$messages = $this->client->lpop($topic, $max_messages);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		if( empty($messages) ){
			return [];
		}

		return \array_map(
			function(string $message) use ($topic): Message {
				return new Message(
					topic: $topic,
					payload: $message
				);
			},
			$messages
		);
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		return;
	}

	/**
	 * @inheritDoc
	 */
	public function nack(Message $message, int $timeout = 0): void
	{
		try {

			$this->publish($message);
		}
		catch( PublisherException $exception ){
			throw new ConsumerException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}