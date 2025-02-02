<?php

namespace Nimbly\Syndicate\Adapter\Queue;

use Throwable;
use Predis\Client;
use Predis\Connection\ConnectionException as RedisConnectionException;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Adapter\ConsumerInterface;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;

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
		catch( RedisConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Redis failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new PublishException(
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
		catch( RedisConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Redis failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumeException(
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
		catch( PublishException $exception ){
			throw new ConsumeException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}