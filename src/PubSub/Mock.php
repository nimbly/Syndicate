<?php

namespace Nimbly\Syndicate\PubSub;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\SubscriberInterface;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;

class Mock implements PublisherInterface, SubscriberInterface
{
	protected bool $isShutdown = false;

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
			throw new PublisherException("Failed to publish message.");
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
		foreach( $this->subscriptions as $topic => $callback ){
			if( !isset($this->messages[$topic]) ){
				continue;
			}

			while( count($this->messages[$topic]) ){
				$messages = \array_splice($this->messages[$topic], 0, 1);
				\call_user_func($callback, $messages[0]);

				if( $this->isShutdown ){
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
		$this->isShutdown = true;
		return;
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
	 * Get the subscription (callback) for a topic.
	 *
	 * @param string $topic The topic name.
	 * @return callable|null Returns null if no subscription does not exist.
	 */
	public function getSubscription(string $topic): ?callable
	{
		return $this->subscriptions[$topic] ?? null;
	}

	/**
	 * Get the shutdown value.
	 *
	 * @return boolean
	 */
	public function getIsShutdown(): bool
	{
		return $this->isShutdown;
	}
}