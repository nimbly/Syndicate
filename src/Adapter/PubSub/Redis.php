<?php

namespace Nimbly\Syndicate\Adapter\PubSub;

use Throwable;
use Predis\Client;
use Predis\PubSub\Consumer;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Adapter\SubscriberInterface;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\SubscriptionException;
use Predis\Connection\ConnectionException as RedisConnectionException;

class Redis implements PublisherInterface, SubscriberInterface
{
	protected ?Consumer $loop = null;

	/**
	 * @var array<string,callable>
	 */
	protected array $subscriptions = [];

	public function __construct(
		protected Client $client,
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
		catch( RedisConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Redis failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ) {
			throw new PublishException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return (string) $result;
	}

	/**
	 * @inheritDoc
	 */
	public function subscribe(string|array $topics, callable $callback, array $options = []): void
	{
		if( !\is_array($topics) ){
			$topics = \array_map(
				fn(string $topic) => \trim($topic),
				\explode(",", $topics)
			);
		}

		foreach( $topics as $channel ){
			$this->subscriptions[$channel] = $callback;
		}

		try {

			$this->getLoop()->subscribe(...$topics);
		}
		catch( RedisConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Redis failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new SubscriptionException(
				message: "Failed to subscribe to topic.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 * @throws ConsumeException
	 */
	public function loop(array $options = []): void
	{
		/**
		 * Because Predis uses fgets() to read from a socket,
		 * it is a hard blocking call. We disable async signals
		 * and manually call pcntl_signal_dispatch() with each
		 * loop. This requires data to be read first from the socket,
		 * so if there is no data, you will still block and wait
		 * until there is data.
		 */
		\pcntl_async_signals(false);

		$loop = $this->getLoop();

		while( $loop->valid() ) {

			try {
				/**
				 * @var object{kind:string,channel:string,payload:string} $msg
				 */
				$msg = $loop->current();
			}
			catch( RedisConnectionException $exception ){
				throw new ConnectionException(
					message: "Connection to Redis failed.",
					previous: $exception
				);
			}
			catch( Throwable $exception ){
				throw new ConsumeException(
					message: "Failed to consume message.",
					previous: $exception
				);
			}

			if( $msg->kind === "message" ){
				$callback = $this->subscriptions[$msg->channel] ?? null;

				if( $callback === null ){
					throw new ConsumeException(
						\sprintf(
							"Message received from channel \"%s\", but no callback defined for it.",
							$msg->channel
						)
					);
				}

				$message = new Message(
					topic: $msg->channel,
					payload: $msg->payload,
					reference: $msg
				);

				\call_user_func($callback, $message);
			}

			\pcntl_signal_dispatch();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function shutdown(): void
	{
		try {

			$this->getLoop()->stop(true);
		}
		catch( Throwable $exception ){
			throw new ConnectionException(
				message: "Failed to shutdown subscriber.",
				previous: $exception
			);
		}
	}

	/**
	 * Get the Redis consumer loop.
	 *
	 * @return Consumer
	 * @throws ConsumeException
	 */
	protected function getLoop(): Consumer
	{
		if( empty($this->loop) ){
			$this->loop = $this->client->pubSubLoop();

			if( empty($this->loop) ){
				throw new ConsumeException("Could not initialize Redis pubsub loop.");
			}
		}

		return $this->loop;
	}
}