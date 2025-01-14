<?php

namespace Nimbly\Syndicate;

use DomainException;
use Nimbly\Resolve\Resolve;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
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
	 * @param LoggerInterface|null $logger A LoggerInterface implementation for additional logging and context.
	 */
	public function __construct(
		protected ConsumerInterface $consumer,
		protected RouterInterface $router,
		protected ?PublisherInterface $deadletter = null,
		protected ?ContainerInterface $container = null,
		protected array $signals = [SIGINT],
		protected ?LoggerInterface $logger = null
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
				throw new UnexpectedValueException(
					\sprintf("Could not attach signal (%i) handler.", $signal)
				);
			}
		}
	}

	/**
	 * Begin listening for new messages.
	 *
	 * @param string $location The location to pull messages from the consumer: topic name, queue name, queue URL, etc
	 * @param integer $max_messages Maximum number of messages to pull at once.
	 * @param integer $nack_timeout If nacking a message, how much timeout/delay before message is able to be reserved again. Also known as "visibility delay".
	 * @param integer $polling_timeout Amount of time in seconds to poll before trying again.
	 * @return void
	 */
	public function listen(string $location, int $max_messages = 1, int $nack_timeout = 0, int $polling_timeout = 10): void
	{
		$this->listening = true;

		$this->logger?->info(
			"[NIMBLY/SYNDICATE] Consumer listening started.",
			[
				"consumer" => $this->consumer::class,
				"location" => $location,
				"max_messages" => $max_messages,
				"nack_timeout" => $nack_timeout,
				"polling_timeout" => $polling_timeout
			]
		);

		while( $this->listening ) {
			$messages = $this->consumer->consume($location, $max_messages, ["timeout" => $polling_timeout]);

			foreach( $messages as $message ){

				$this->logger?->info(
					"[NIMBLY/SYNDICATE] Dipatching message.",
					[
						"topic" => $message->getTopic(),
					]
				);

				$response = $this->dispatch($message);

				switch( $response ){
					case Response::nack:
						$this->consumer->nack($message, $nack_timeout);
						break;

					case Response::deadletter:
						if( empty($this->deadletter) ){
							$this->consumer->nack($message, $nack_timeout);
							throw new RoutingException("Cannot route message to deadletter as no deadletter implementation was given.");
						}

						$this->deadletter->publish($message);

					default:
						$this->consumer->ack($message);
				}
			}
		}
	}

	/**
	 * Dispatch a Message to be handled.
	 *
	 * @param Message $message
	 * @return mixed
	 */
	public function dispatch(Message $message): mixed
	{
		$handler = $this->router->resolve($message);

		if( empty($handler) ){
			$this->logger?->warning(
				"[NIMBLY/SYNDICATE] A handler could not be resolved for this Message: attempting to deadletter.",
				[
					"topic" => $message->getTopic(),
					"payload" => $message->getPayload(),
					"attributes" => $message->getAttributes(),
					"headers" => $message->getHeaders(),
					"reference" => $message->getReference(),
				]
			);

			return Response::deadletter;
		}

		return $this->call(
			$this->makeCallable($handler),
			$this->container,
			[Message::class => $message]
		);
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