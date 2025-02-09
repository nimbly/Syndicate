<?php

namespace Nimbly\Syndicate\Adapter;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;

/**
 * A Mock queue adapter that can be used for testing. This
 * adapter does not persist or send messages to any external
 * service. Messages are stored in memory.
 */
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
		if( isset($options["exception"]) ){
			throw new PublishException("Failed to publish message.");
		}

		$this->messages[$message->getTopic()][] = $message;
		return \bin2hex(\random_bytes(12));
	}

	/**
	 * @inheritDoc
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		if( isset($options["exception"]) ){
			throw new ConsumeException("Failed to consume messages.");
		}

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
	 * @return array<Message>
	 */
	public function getMessages(string $topic): array
	{
		return $this->messages[$topic] ?? [];
	}

	/**
	 * Flush messages for a given topic or all topics.
	 *
	 * @param string|null $topic The topic to flush messages for. If `null` flush all topics.
	 *
	 * @return void
	 */
	public function flushMessages(?string $topic = null): void
	{
		if( $topic === null ){
			$this->messages = [];
		}
		else {
			$this->messages[$topic] = [];
		}
	}
}