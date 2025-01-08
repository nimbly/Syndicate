<?php

namespace Nimbly\Syndicate\Queue;

use Aws\Sqs\SqsClient;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Throwable;

class Sqs implements PublisherInterface, ConsumerInterface
{
	public function __construct(
		protected SqsClient $client
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$message = [
			"QueueUrl" => $message->getTopic(),
			"MessageBody" => $message->getPayload(),
			"MessageAttributes" => $message->getAttributes(),
			...$options
		];

		try {

			$result = $this->client->sendMessage($message);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return (string) $result->get("MessageId");
	}

	/**
	 * @inheritDoc
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		try {

			$result = $this->client->receiveMessage([
				"QueueUrl" => $topic,
				"MaxNumberOfMessages" => $max_messages,
				...$options
			]);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		$messages = \array_map(
			function(array $message) use ($topic): Message {
				return new Message(
					topic: $topic,
					payload: $message["Body"],
					//attributes: $message["Attributes"],
					reference: $message["ReceiptHandle"]
				);
			},
			$result->get("Messages") ?? []
		);

		return $messages;
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		$request = [
			"QueueUrl" => $message->getTopic(),
			"ReceiptHandle" => $message->getReference(),
		];

		try {

			$this->client->deleteMessage($request);
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
		$request = [
			"QueueUrl" => $message->getTopic(),
			"ReceiptHandle" => $message->getReference(),
			"VisibilityTimeout" => $timeout,
		];

		try {

			$this->client->changeMessageVisibility($request);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}