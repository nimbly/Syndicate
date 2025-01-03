<?php

namespace Nimbly\Syndicate;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMQ implements PublisherInterface, ConsumerInterface, LoopConsumerInterface
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
		$messages = [];

		for( $i = 0; $i < $max_messages; $i++ ){

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

			if( $message ){
				$messages[] = new Message(
					topic: $topic,
					payload: $message->getBody(),
					reference: $message
				);
			}
		}

		return $messages;
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

			$rabbitMessage->reject();
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function subscribe(string|array $topic, callable $callback, array $options = []): void
	{
		try {

			$this->channel->basic_consume(
				queue: \is_array($topic) ? $topic[0] : $topic,
				callback: $callback,
				consumer_tag: $options["consumer_tag"] ?? "",
				no_local: $options["no_local"] ?? false,
				no_ack: $options["no_ack"] ?? false,
				exclusive: $options["exclusive"] ?? false,
				nowait: $options["nowait"] ?? false,
				ticket: $options["ticket"] ?? null,
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to subscribe to topic.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function loop(array $options = []): void
	{
		try {

			$this->channel->consume($options["timeout"] ?? 10);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function shutdown(): void
	{
		try {

			$this->channel->stopConsume();
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to shutdown consumer.",
				previous: $exception
			);
		}
	}
}