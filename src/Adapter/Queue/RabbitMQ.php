<?php

namespace Nimbly\Syndicate\Adapter\Queue;

use Throwable;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Adapter\ConsumerInterface;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;

class RabbitMQ implements PublisherInterface, ConsumerInterface
{
	public function __construct(
		protected AMQPChannel $channel)
	{
	}

	/**
	 * @inheritDoc
	 * @return null
	 *
	 * Message attributes:
	 * 	* `exchange` (string) Defaults to empty string "".
	 *  * `mandatory` (boolean) Defaults to false.
	 *  * `immediate` (boolean) Defaults to false.
	 *  * `ticket` (?string) Defaults to null.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$this->channel->basic_publish(
				msg: new AMQPMessage($message->getPayload(), $message->getAttributes()),
				exchange: $message->getAttributes()["exchange"] ?? "",
				routing_key: $message->getTopic(),
				mandatory: $message->getAttributes()["mandatory"] ?? false,
				immediate: $message->getAttributes()["immediate"] ?? false,
				ticket: $message->getAttributes()["ticket"] ?? null,
			);
		}
		catch( AMQPConnectionClosedException|AMQPConnectionBlockedException $exception ){
			throw new ConnectionException(
				message: "Connection to RabbitMQ failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new PublishException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return null;
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `no_ack` (boolean) Do not automatically ACK messages as they are pulled off the queue. Defaults to true.
	 *  * `ticket` (?string) Defaults to null.
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		try {

			$message = $this->channel->basic_get(
				queue: $topic,
				no_ack: $options["no_ack"] ?? true,
				ticket: $options["ticket"] ?? null,
			);
		}
		catch( AMQPConnectionClosedException|AMQPConnectionBlockedException $exception ){
			throw new ConnectionException(
				message: "Connection to RabbitMQ failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumeException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		if( empty($message) ){
			return [];
		}

		return [
			new Message(
				topic: $topic,
				payload: $message->getBody(),
				reference: $message
			)
		];
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		/**
		 * @var AMQPMessage $rabbitMessage
		 */
		$rabbitMessage = $message->getReference();

		try {

			$rabbitMessage->ack();
		}
		catch( AMQPConnectionClosedException|AMQPConnectionBlockedException $exception ){
			throw new ConnectionException(
				message: "Connection to RabbitMQ failed.",
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
		/**
		 * @var AMQPMessage $rabbitMessage
		 */
		$rabbitMessage = $message->getReference();

		try {

			$rabbitMessage->reject(true);
		}
		catch( AMQPConnectionClosedException|AMQPConnectionBlockedException $exception ){
			throw new ConnectionException(
				message: "Connection to RabbitMQ failed.",
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