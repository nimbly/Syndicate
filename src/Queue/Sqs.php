<?php

namespace Nimbly\Syndicate\Queue;

use Throwable;
use Aws\Sqs\SqsClient;
use Nimbly\Syndicate\Message;
use Aws\Exception\CredentialsException;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\ConnectionException;

class Sqs implements PublisherInterface, ConsumerInterface
{
	/**
	 * @param SqsClient $client
	 * @param string|null $base_url An optional base URL if you are publishing or consuming all messages to the same queue. With this option set, when you publish a message or consume, its topic does not need to include the base URL portion.
	 */
	public function __construct(
		protected SqsClient $client,
		protected ?string $base_url = null
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$message = [
			"QueueUrl" => $this->base_url ?? "" . $message->getTopic(),
			"MessageBody" => $message->getPayload(),
			"MessageAttributes" => $message->getAttributes(),
			...$options
		];

		try {

			$result = $this->client->sendMessage($message);
		}
		catch( CredentialsException $exception ){
			throw new ConnectionException(
				message: "Connection to SQS failed.",
				previous: $exception
			);
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
				"QueueUrl" => $this->base_url ?? "" . $topic,
				"MaxNumberOfMessages" => $max_messages,
				...$options
			]);
		}
		catch( CredentialsException $exception ){
			throw new ConnectionException(
				message: "Connection to SQS failed.",
				previous: $exception
			);
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
		catch( CredentialsException $exception ){
			throw new ConnectionException(
				message: "Connection to SQS failed.",
				previous: $exception
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
		$request = [
			"QueueUrl" => $message->getTopic(),
			"ReceiptHandle" => $message->getReference(),
			"VisibilityTimeout" => $timeout,
		];

		try {

			$this->client->changeMessageVisibility($request);
		}
		catch( CredentialsException $exception ){
			throw new ConnectionException(
				message: "Connection to SQS failed.",
				previous: $exception
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