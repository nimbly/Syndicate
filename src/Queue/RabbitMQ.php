<?php

namespace Nimbly\Syndicate\Queue;

use Throwable;
use Nimbly\Syndicate\Message;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;

class RabbitMQ implements PublisherInterface, ConsumerInterface
{
	public function __construct(
		protected AMQPChannel $channel)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
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
				exchange: $options["exchange"] ?? "",
				routing_key: $message->getTopic(),
				mandatory: $options["mandatory"] ?? false,
				immediate: $options["immediate"] ?? false,
				ticket: $options["ticket"] ?? null,
			);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
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
		catch( Throwable $exception ){
			throw new ConsumerException(
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
		/**
		 * @var AMQPMessage $rabbitMessage
		 */
		$rabbitMessage = $message->getReference();

		try {

			$rabbitMessage->reject(true);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}