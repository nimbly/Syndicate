<?php

namespace Syndicate\Queue;

use MicrosoftAzure\Storage\Queue\Models\ListMessagesOptions;
use MicrosoftAzure\Storage\Queue\Models\QueueMessage;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use Syndicate\Message;

/**
 *
 * @property QueueRestProxy $client
 *
 */
class Azure extends Queue
{
	/**
	 * Azure constructor.
	 *
	 * @param string $name
	 * @param QueueRestProxy $queueRestProxy
	 */
	public function __construct(string $name, QueueRestProxy $queueRestProxy)
	{
		$this->name = $name;
		$this->client = $queueRestProxy;

	}

	/**
	 * @inheritDoc
	 */
	public function put($data, array $options = []): void
	{
		$this->client->createMessage(
			$this->name,
			$this->serialize($data)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get(array $options = []): ?Message
	{
		$messages = $this->many(1, $options);
		return $messages[0] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function many(int $max, array $options = []): array
	{
		$listMessageOptions = new ListMessagesOptions;
		$listMessageOptions->setNumberOfMessages($max);

		if( !empty($options) ){
			if( isset($options['delay']) ){
				$listMessageOptions->setVisibilityTimeoutInSeconds((int) $options['delay']);
			}

			if( isset($options['timeout']) ){
				$listMessageOptions->setTimeout($options['timeout']);
			}
		}

		$listMessageResult = $this->client->listMessages(
			$this->name,
			$listMessageOptions
		);

		return \array_reduce(
			$listMessageResult->getQueueMessages(),
			function(array $messages, QueueMessage $queueMessage): array {
				$messages[] = new Message($this, $queueMessage, $this->deserialize($queueMessage->getMessageText()));
				return $messages;
			},
			[]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function delete(Message $message): void
	{
		/** @var QueueMessage $queueMessage */
		$queueMessage = $message->getSourceMessage();

		$this->client->deleteMessage(
			$this->name,
			$queueMessage->getMessageId(),
			$queueMessage->getPopReceipt()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function release(Message $message, array $options = []): void
	{
		/** @var QueueMessage $queueMessage */
		$queueMessage = $message->getSourceMessage();

		$this->client->updateMessage(
			$this->name,
			$queueMessage->getMessageId(),
			$queueMessage->getPopReceipt(),
			$queueMessage->getMessageText(),
			(int) ($options['delay'] ?? 0)
		);
	}
}