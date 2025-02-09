<?php

namespace Nimbly\Syndicate\Adapter;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\PublishException;

/**
 * A Mock pubsub adapter that can be used for testing. This adapter
 * does not send messages to any external service. Messages are
 * stored in memory.
 */
class MockPubSub implements PublisherInterface, SubscriberInterface
{
	protected bool $running = false;

	/**
	 * @param array<string,array<Message>> $messages Array of preloaded messages in queue, indexed by topic.
	 * @param array<string,callable> $subscriptions Array of topic names mapped to a callable.
	 */
	public function __construct(
		protected array $messages = [],
		protected array $subscriptions = [])
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
	public function subscribe(string|array $topics, callable $callback, array $options = []): void
	{
		if( \is_string($topics) ){
			$topics = \array_map(
				fn(string $topic) => \trim($topic),
				\explode(",", $topics)
			);
		}

		foreach( $topics as $topic ){
			$this->subscriptions[$topic] = $callback;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function loop(array $options = []): void
	{
		$this->running = true;

		foreach( $this->subscriptions as $topic => $callback ){
			if( !isset($this->messages[$topic]) ){
				continue;
			}

			while( count($this->messages[$topic]) ){
				$messages = \array_splice($this->messages[$topic], 0, 1);
				\call_user_func($callback, $messages[0]);

				/**
				 * @psalm-suppress TypeDoesNotContainType
				 */
				if( $this->running === false ){
					return;
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function shutdown(): void
	{
		$this->running = false;
	}

	/**
	 * Get all the messages in a topic.
	 *
	 * @param string $topic The topic name.
	 * @return array<Message> Returns all pending messages in the topic.
	 */
	public function getMessages(string $topic): array
	{
		return $this->messages[$topic] ?? [];
	}

	/**
	 * Flush messages for a given topic or all topics.
	 *
	 * @param string|null $topic The topic to flush messages for. If `null` flush all topics.
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

	/**
	 * Get the subscription (callback) for a topic.
	 *
	 * @param string $topic The topic name.
	 * @return callable|null Returns `null` if no subscriptions exist for given topic.
	 */
	public function getSubscription(string $topic): ?callable
	{
		return $this->subscriptions[$topic] ?? null;
	}

	/**
	 * Get the running value.
	 *
	 * @return boolean
	 */
	public function getRunning(): bool
	{
		return $this->running;
	}
}