<?php

namespace Nimbly\Syndicate\PubSub;

use Nimbly\Syndicate\ConsumerException;
use Throwable;
use Predis\Client;
use Predis\PubSub\Consumer;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\LoopConsumerInterface;

class Redis implements PublisherInterface, LoopConsumerInterface
{
	protected ?Consumer $loop = null;

	/**
	 * @var array<string,callable>
	 */
	protected array $subscriptions = [];
	protected bool $listening = false;

	public function __construct(
		protected Client $client
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$result = $this->client->publish(
				$message->getTopic(),
				$message->getPayload()
			);
		}
		catch( Throwable $exception ) {
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return (string) $result;
	}

	/**
	 * @inheritDoc
	 */
	public function subscribe(string|array $topic, callable $callback, array $options = []): void
	{
		if( !\is_array($topic) ){
			$topic = [$topic];
		}

		foreach( $topic as $t ){
			$this->subscriptions[$t] = $callback;
			$this->getLoop()->subscribe($t);
		}
	}

	/**
	 * @inheritDoc
	 * @throws ConsumerException
	 */
	public function loop(array $options = []): void
	{
		$this->listening = true;

		/**
		 * @var object{kind:string,channel:string,payload:string} $msg
		 */
		foreach( $this->getLoop() as $msg ){
			if( $msg->kind === "message" ){
				$callback = $this->subscriptions[$msg->channel] ?? null;

				if( !empty($callback) ){
					\call_user_func($callback, $msg);
				}
			}

			if( !$this->listening ){
				$this->getLoop()->stop(true);
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function shutdown(): void
	{
		$this->listening = false;
	}

	/**
	 * Get the Redis consumer loop.
	 *
	 * @return Consumer
	 * @throws ConsumerException
	 */
	private function getLoop(): Consumer
	{
		if( empty($this->loop) ){
			$this->loop = $this->client->pubSubLoop();

			if( empty($this->loop) ){
				throw new ConsumerException("Could not initialize Redis pubsub loop.");
			}
		}

		return $this->loop;
	}
}