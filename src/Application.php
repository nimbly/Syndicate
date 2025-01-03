<?php

namespace Nimbly\Syndicate;

use DomainException;
use Nimbly\Resolve\Resolve;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

class Application
{
	use Resolve;

	protected bool $listening = false;

	/**
	 * @param ConsumerInterface $consumer The consumer to pull messages from.
	 * @param RouterInterface $router A router instance that will aid resolving Messages received into callables.
	 * @param PublisherInterface|null $deadletter A deadletter publisher instance if you would like to use one.
	 * @param ContainerInterface|null $container An optional container instance to be used when invoking the handler.
	 * @param array<int> $signals Array of PHP signal constants to trigger a graceful shutdown. Defaults to SIGINT.
	 */
	public function __construct(
		protected ConsumerInterface $consumer,
		protected RouterInterface $router,
		protected ?PublisherInterface $deadletter = null,
		protected ?ContainerInterface $container = null,
		protected array $signals = [SIGINT]
	)
	{
		if( !\extension_loaded("pcntl") ){
			throw new DomainException("The ext-pcntl module must be installed to use Syndicate.");
		}

		\pcntl_async_signals(true);

		foreach( $signals as $signal ){
			$result = \pcntl_signal(
				$signal,
				[$this, "shutdown"]
			);

			if( $result === false ){
				throw new UnexpectedValueException("Could not attach signal (" . $signal . ") handler.");
			}
		}
	}

	/**
	 * Begin listening for new messages.
	 *
	 * @param string $location The location to pull messages from the consumer: topic name, queue name, queue URL, etc
	 * @param integer $max_messages Maximum number of messages to pull at once.
	 * @param integer $nack_timeout If nacking a message and how much timeout/delay before message is able to be reserved again. Also known as "visibility delay".
	 * @param integer $polling_timeout Amount of time in seconds to poll before trying again.
	 * @return void
	 */
	public function listen(string $location, int $max_messages = 1, int $nack_timeout = 0, int $polling_timeout = 10): void
	{
		$this->listening = true;

		while( $this->listening ) {
			$messages = $this->consumer->consume($location, $max_messages, ["timeout" => $polling_timeout]);

			if( $messages ) {
				foreach( $messages as $message ){
					$handler = $this->router->resolve($message);

					if( empty($handler) ){
						$this->consumer->nack($message, $nack_timeout);
						throw new RoutingException("Failed to resolve route for message.");
					}

					$response = $this->call(
						$this->makeCallable($handler),
						$this->container,
						[Message::class => $message]
					);

					switch( $response ){
						case Response::nack:
							$this->consumer->nack($message, $nack_timeout);
							break;

						case Response::deadleter:
							if( empty($this->deadletter) ){
								throw new PublisherException("Cannot publish message to deadletter as no deadletter implementation was given.");
							}

							$this->deadletter->publish($message);

						default:
							$this->consumer->ack($message);
					}
				}
			}
		}
	}

	/**
	 * Shutdown the listener.
	 *
	 * @return void
	 */
	public function shutdown(): void
	{
		$this->listening = false;
	}
}