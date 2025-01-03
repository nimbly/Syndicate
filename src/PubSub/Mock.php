<?php

namespace Nimbly\Syndicate\PubSub;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\PublisherInterface;

class Mock implements PublisherInterface, ConsumerInterface
{
	/**
	 * @param array<string,array<Message>> $messages Array of preloaded messages in queue, indexed by topic.
	 */
	public function __construct(
		protected array $messages = [])
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$this->messages[$message->getTopic()][] = $message;
		return \bin2hex(\random_bytes(12));
	}

	/**
	 * @inheritDoc
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		if( !\array_key_exists($topic, $this->messages) ){
			return [];
		}

		$messages = \array_splice($this->messages[$topic], 0, $max_messages);

		return $messages;
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
		$this->publish($message);
	}

	/**
	 * Get all the messages in a topic.
	 *
	 * @param string $topic
	 * @return array
	 */
	public function getMessages(string $topic): array
	{
		return $this->messages[$topic] ?? [];
	}
}