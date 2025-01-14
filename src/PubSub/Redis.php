<?php

namespace Nimbly\Syndicate\PubSub;

use Throwable;
use Predis\Client;
use Nimbly\Resolve\Resolve;
use Predis\PubSub\Consumer;
use Nimbly\Syndicate\Message;
use Psr\Container\ContainerInterface;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\ConnectionException;
use Nimbly\Syndicate\LoopConsumerInterface;
use Predis\Connection\ConnectionException as RedisConnectionException;

class Redis implements PublisherInterface, LoopConsumerInterface
{
	use Resolve;

	protected ?Consumer $loop = null;

	/**
	 * @var array<string,callable>
	 */
	protected array $subscriptions = [];

	public function __construct(
		protected Client $client,
		protected ?ContainerInterface $container = null
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
	public function subscribe(string|array $topic, string|callable $callback, array $options = []): void
	{
		if( !\is_array($topic) ){
			$topic = [$topic];
		}

		try {

			foreach( $topic as $channel ){
				$callback = $this->makeCallable($callback);

				$this->subscriptions[$channel] = function(Message $message) use ($callback): void {
					$this->call(
						$callback,
						$this->container,
						[Message::class => $message]
					);
				};
			}

			$this->getLoop()->subscribe(...$topic);
		}
		catch( RedisConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Redis failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to subscribe to topic.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 * @throws ConsumerException
	 */
	public function loop(array $options = []): void
	{
		try {

			$loop = $this->getLoop();

			/**
			 * @var object{kind:string,channel:string,payload:string} $msg
			 */
			foreach( $loop as $msg ){
				if( $msg->kind === "message" ){
					$callback = $this->subscriptions[$msg->channel] ?? null;

					if( empty($callback) ){
						throw new ConsumerException("Message received from channel, but no callback defined for it: " . $msg->channel . ".");
					}

					\call_user_func(
						$callback,
						new Message(
							topic: $msg->channel,
							payload: $msg->payload,
							reference: $msg
						)
					);
				}
			}
		}
		catch( RedisConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Redis failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
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
		catch( RedisConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Redis failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to shutdown consumer loop.",
				previous: $exception
			);
		}
	}

	/**
	 * Get the Redis consumer loop.
	 *
	 * @return Consumer
	 * @throws ConsumerException
	 */
	protected function getLoop(): Consumer
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