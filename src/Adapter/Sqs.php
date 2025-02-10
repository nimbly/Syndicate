<?php

namespace Nimbly\Syndicate\Adapter;

use Throwable;
use Aws\Sqs\SqsClient;
use Aws\Exception\CredentialsException;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;

class Sqs implements PublisherInterface, ConsumerInterface
{
	/**
	 * @param SqsClient $client
	 * @param string|null $base_url An optional base URL if you are publishing or consuming all messages to the same AWS account. With this option set, when you publish a message or consume, its topic does not need to include the base URL portion.
	 */
	public function __construct(
		protected SqsClient $client,
		protected ?string $base_url = null
	)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * Message attributes:
	 * * `MessageGroupId` (string) The message group ID.
	 * * `MessageDeduplicationId` (string) The message deduplication ID.
	 * * `any` All other values will be sent as `MessageAttributes` and must adhere to SQS guidelines for Attributes. @see See https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-message-metadata.html for more information.
	 *
	 * Options:
	 * *`any` All options will be passed directly through to SQS call.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$args = $this->buildPublishArguments($message, $options);

		try {

			$result = $this->client->sendMessage($args);
		}
		catch( CredentialsException $exception ){
			throw new ConnectionException(
				message: "Connection to SQS failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new PublishException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return (string) $result->get("MessageId");
	}

	/**
	 * Build the arguments array needed to call SQS when publishing a message.
	 *
	 * @param Message $message
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>
	 */
	private function buildPublishArguments(Message $message, array $options = []): array
	{
		$attributes = \array_filter(
			$message->getAttributes(),
			fn(string $key) => !\in_array($key, ["MessageGroupId", "MessageDeduplicationId"]),
			ARRAY_FILTER_USE_KEY
		);

		$args = \array_filter([
			"QueueUrl" => $this->base_url ?? "" . $message->getTopic(),
			"MessageBody" => $message->getPayload(),
			"MessageGroupId" => $message->getAttributes()["MessageGroupId"] ?? null,
			"MessageDeduplicationId" => $message->getAttributes()["MessageDeduplicationId"] ?? null,
			"MessageAttributes" => $attributes,
			...$options,
		]);

		return $args;
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
			throw new ConsumeException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		$messages = \array_map(
			function(array $message) use ($topic): Message {
				return new Message(
					topic: $topic,
					payload: $message["Body"],
					attributes: $message["Attributes"],
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
			throw new ConsumeException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}