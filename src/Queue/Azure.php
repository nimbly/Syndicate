<?php

namespace Nimbly\Syndicate\Queue;

use MicrosoftAzure\Storage\Queue\Models\ListMessagesOptions;
use MicrosoftAzure\Storage\Queue\Models\QueueMessage;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Throwable;

class Azure implements PublisherInterface, ConsumerInterface
{
	/**
	 * @param QueueRestProxy $client
	 */
	public function __construct(
		protected QueueRestProxy $client)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$result = $this->client->createMessage(
				$message->getTopic(),
				$message->getPayload(),
			);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return $result->getQueueMessage()->getMessageId();
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `delay` (integer) Visibility timeout in seconds.
	 * 	* `timeout` (integer) Polling timeout in seconds.
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		try {

			$listMessageResult = $this->client->listMessages(
				$topic,
				$this->buildListMessageOptions($max_messages, $options)
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		$messages = \array_map(
			function(QueueMessage $queueMessage) use ($topic): Message {
				return new Message(
					topic: $topic,
					payload: $queueMessage->getMessageText(),
					reference: [$queueMessage->getMessageId(), $queueMessage->getPopReceipt()],
				);
			},
			$listMessageResult->getQueueMessages()
		);

		return $messages;
	}

	/**
	 * Build the Azure ListMessageOptions object for consuming messages.
	 *
	 * @param integer $max_messages
	 * @param array $options
	 * @return ListMessagesOptions
	 */
	protected function buildListMessageOptions(int $max_messages, array $options): ListMessagesOptions
	{
		$listMessageOptions = new ListMessagesOptions;
		$listMessageOptions->setNumberOfMessages($max_messages);

		if( \array_key_exists("delay", $options) ){
			$listMessageOptions->setVisibilityTimeoutInSeconds((int) $options["delay"]);
		}

		if( \array_key_exists("timeout", $options) ){
			$listMessageOptions->setTimeout($options["timeout"]);
		}

		return $listMessageOptions;
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		[$message_id, $pop_receipt] = $message->getReference();

		try {

			$this->client->deleteMessage(
				$message->getTopic(),
				$message_id,
				$pop_receipt
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
		[$message_id, $pop_receipt] = $message->getReference();

		try {

			$this->client->updateMessage(
				$message->getTopic(),
				$message_id,
				$pop_receipt,
				$message->getPayload(),
				$timeout
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