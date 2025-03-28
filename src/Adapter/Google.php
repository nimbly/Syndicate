<?php

namespace Nimbly\Syndicate\Adapter;

use Throwable;
use Google\Cloud\PubSub\Message as GoogleMessage;
use Google\Cloud\PubSub\PubSubClient;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;

class Google implements PublisherInterface, ConsumerInterface
{
	/**
	 * @param PubSubClient $client
	 */
	public function __construct(
		protected PubSubClient $client)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$topic = $this->client->topic($message->getTopic(), $options);

		$options["headers"] = $message->getHeaders();

		try {

			$result = $topic->publish(
				[
					"data" => $message->getPayload(),
					"attributes" => $message->getAttributes(),
				],
				$options
			);
		}
		catch( Throwable $exception ){
			throw new PublishException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return $result[0];
	}

	/**
	 * The `topic` for Google PubSub is actually the subscription name. Therefore you must
	 * create the subscription first before using this method.
	 *
	 * @inheritDoc
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		$subscription = $this->client->subscription($topic);

		try {

			$response = $subscription->pull([
				"maxMessages" => $max_messages,
				...$options
			]);
		}
		catch( Throwable $exception ) {
			throw new ConsumeException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		$messages = \array_map(
			function(GoogleMessage $message): Message {
				return new Message(
					topic: $message->subscription()->name(),
					payload: $message->data(),
					attributes: $message->attributes(),
					reference: $message,
				);
			},
			$response
		);

		return $messages;
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		$subscription = $this->client->subscription($message->getTopic());

		try {

			$subscription->acknowledge($message->getReference());
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
	}
}