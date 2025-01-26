<?php

namespace Nimbly\Syndicate;

use Nimbly\Resolve\Resolve;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use Psr\Container\ContainerInterface;
use Nimbly\Syndicate\Router\RouterInterface;
use Nimbly\Syndicate\Router\RoutingException;
use Nimbly\Syndicate\Middleware\MiddlewareInterface;

class Application
{
	use Resolve;

	protected bool $listening = false;

	/**
	 * The compiled middleware chain.
	 *
	 * @var callable
	 */
	protected $middleware;

	/**
	 * @param ConsumerInterface|SubscriberInterface $consumer The consumer to pull messages from.
	 * @param RouterInterface $router A router instance that will aid resolving Messages received into callables.
	 * @param PublisherInterface|null $deadletter A deadletter publisher instance if you would like to use one.
	 * @param ContainerInterface|null $container An optional container instance to be used when invoking the handler.
	 * @param LoggerInterface|null $logger A LoggerInterface implementation for additional logging and context.
	 * @param array<MiddlewareInterface|class-string> $middleware An array of MiddlewareInterface instances or a class-string of a MiddlewareInterface implementation.
	 * @param array<int> $signals Array of PHP signal constants to trigger a graceful shutdown. Defaults to [SIGINT, SIGTERM].
	 */
	public function __construct(
		protected ConsumerInterface|SubscriberInterface $consumer,
		protected RouterInterface $router,
		protected ?PublisherInterface $deadletter = null,
		protected ?ContainerInterface $container = null,
		protected ?LoggerInterface $logger = null,
		array $middleware = [],
		protected array $signals = [SIGINT, SIGTERM],
	)
	{
		/**
		 * Attach interrupt handlers.
		 */
		$this->attachInterruptSignals($signals);

		/**
		 * Compile the middleware using the `dispatch` method as the kernel.
		 */
		$this->middleware = $this->compileMiddleware($middleware, [$this, "dispatch"]);
	}

	/**
	 * Begin listening for new messages.
	 *
	 * @param string|array<string> $location The location to pull messages from the consumer: topic name, queue name, queue URL, etc. If using a `SubscriberInterface`, you can pass an array of topics to listen on.
	 * @param integer $max_messages Maximum number of messages to pull at once.
	 * @param integer $nack_timeout If nacking a message, how much timeout/delay before message is able to be reserved again. Also known as "visibility delay".
	 * @param integer $polling_timeout Amount of time in seconds to poll before trying again.
	 * @param array<string,mixed> $deadletter_options Options to be passed when publishing a message to the deadletter publisher.
	 * @throws ConnectionException
	 * @throws ConsumerException
	 * @throws PublisherException
	 * @return void
	 */
	public function listen(string|array $location, int $max_messages = 1, int $nack_timeout = 0, int $polling_timeout = 10, array $deadletter_options = []): void
	{
		$this->listening = true;

		if( $this->consumer instanceof SubscriberInterface ){

			$this->consumer->subscribe($location, $this->middleware);
			$this->logger?->info(
				"[NIMBLY/SYNDICATE] Loop consumer listening started.",
				[
					"consumer" => $this->consumer::class,
					"topics" => $location
				]
			);

			$this->consumer->loop();
		}
		else {

			if( \is_array($location) ){
				throw new UnexpectedValueException(
					\sprintf(
						"The %s consumer cannot listen from more than one location.",
						$this->consumer::class
					)
				);
			}

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

			/**
			 * @psalm-suppress RedundantCondition
			 */
			while( $this->listening ) {

				$messages = $this->consumer->consume($location, $max_messages, ["timeout" => $polling_timeout]);

				foreach( $messages as $message ){

					$this->logger?->debug(
						"[NIMBLY/SYNDICATE] Dispatching message.",
						[
							"topic" => $message->getTopic(),
							"payload" => $message->getPayload(),
							"attributes" => $message->getAttributes(),
							"headers" => $message->getHeaders(),
							"reference" => $message->getReference(),
						]
					);

					$response = \call_user_func($this->middleware, $message);

					switch( $response ){
						case Response::nack:
							$this->logger?->debug(
								"[NIMBLY/SYNDICATE] Nacking message.",
								[
									"topic" => $message->getTopic(),
									"payload" => $message->getPayload(),
									"headers" => $message->getHeaders(),
									"attributes" => $message->getAttributes(),
								]
							);

							$this->consumer->nack($message, $nack_timeout);
							break;

						case Response::deadletter:
							$this->logger?->warning(
								"[NIMBLY/SYNDICATE] Deadlettering message.",
								[
									"topic" => $message->getTopic(),
									"payload" => $message->getPayload(),
									"headers" => $message->getHeaders(),
									"attributes" => $message->getAttributes(),
								]
							);

							if( $this->deadletter === null ){
								$this->consumer->nack($message, $nack_timeout);
								throw new RoutingException("Cannot route message to deadletter as no deadletter implementation was given.");
							}

							$this->deadletter->publish($message, $deadletter_options);

						default:
							$this->logger?->debug(
								"[NIMBLY/SYNDICATE] Acking message.",
								[
									"topic" => $message->getTopic(),
									"payload" => $message->getPayload(),
									"headers" => $message->getHeaders(),
									"attributes" => $message->getAttributes(),
								]
							);

							$this->consumer->ack($message);
					}
				}
			}
		}

		$this->logger?->info("[NIMBLY/SYNDICATE] In flight messages drained. Shutting down.");
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

		if( $handler === null ){
			$this->logger?->warning(
				"[NIMBLY/SYNDICATE] A handler could not be resolved for this message.",
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
	public function shutdown(?int $signal = null): void
	{
		if( $this->listening ){
			$this->logger?->info(
				"[NIMBLY/SYNDICATE] Interrupt signal received. Draining in-flight messages.",
				["signal" => $signal]
			);

			$this->listening = false;

			if( $this->consumer instanceof SubscriberInterface ){
				$this->consumer->shutdown();
			}
		}
	}

	/**
	 * Build a middleware chain out of middleware using provided Kernel as the final handler.
	 *
	 * @param array<MiddlewareInterface|class-string> $middleware
	 * @param callable $kernel
	 * @return callable
	 */
	protected function compileMiddleware(array $middleware, callable $kernel): callable
	{
		return \array_reduce(
			$this->normalizeMiddleware(\array_reverse($middleware)),
			function(callable $handler, MiddlewareInterface $middleware): callable {
				return function(Message $message) use ($handler, $middleware): mixed {
					return $middleware->handle($message, $handler);
				};
			},
			$kernel
		);
	}

	/**
	 * Normalize the given middlewares into instances of MiddlewareInterface.
	 *
	 * @param array<MiddlewareInterface|class-string> $middlewares
	 * @throws UnexpectedValueException
	 * @return array<MiddlewareInterface>
	 */
	protected function normalizeMiddleware(array $middlewares): array
	{
		return \array_map(
			function(MiddlewareInterface|string $middleware): MiddlewareInterface {

				if( \is_string($middleware) ){
					$middleware = $this->make($middleware, $this->container);
				}

				if( $middleware instanceof MiddlewareInterface === false ){
					throw new UnexpectedValueException(
						\sprintf(
							"Provided middleware must be an instance of Nimbly\Syndicate\Middleware\MiddlewareInterface or ".
							"a class-string that references a Nimbly\Syndicate\Middleware\MiddlewareInterface implementation. ".
							"%s was given.",
							$middleware::class
						)
					);
				}

				return $middleware;

			},
			$middlewares
		);
	}

	/**
	 * Attach interrupt signals.
	 *
	 * @param array<int> $signals
	 * @return void
	 */
	protected function attachInterruptSignals(array $signals = []): void
	{
		if( !\extension_loaded("pcntl") ){
			$this->logger?->warning(
				"[NIMBLY/SYNDICATE] The pcntl PHP extension doesn't appear to be installed. " .
				"It is highly recommended to install this extension. Without this extension, " .
				"terminating the consumer listener will likely result in messages left in flight, " .
				"lost messages, or messages that were only partially processed."
			);
		}
		else {

			if( empty($signals) ){
				$this->logger?->warning(
					"[NIMBLY/SYNDICATE] No interrupt signals were given. " .
					"It is highly recommended to have one or more interrupt signals to enable a graceful shutdown. " .
					"Without any interrupt signals, terminating the consumer listener will likely result in messages " .
					"left in flight, lost messages, or messages that were only partially processed."
				);
			}

			\pcntl_async_signals(true);

			foreach( $signals as $signal ){
				$result = \pcntl_signal(
					$signal,
					[$this, "shutdown"]
				);

				if( $result === false ){
					throw new UnexpectedValueException(
						\sprintf("Could not attach signal (%s) handler.", (string) $signal)
					);
				}
			}
		}
	}
}