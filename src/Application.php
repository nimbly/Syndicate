<?php

namespace Nimbly\Syndicate;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use Nimbly\Resolve\Resolve;
use Nimbly\Syndicate\Router\RouterInterface;
use Nimbly\Syndicate\Adapter\ConsumerInterface;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\RoutingException;
use Nimbly\Syndicate\Adapter\SubscriberInterface;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Middleware\MiddlewareInterface;
use Nimbly\Syndicate\Exception\SubscriptionException;

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
	 * @param RouterInterface $router An array of class-strings representing your handlers or a full `RouterInterface` instance that will aid resolving Messages received into callables.
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
	 * @param int $max_messages The number of messages to pull off at once.
	 * @param array<string,mixed> $options Options to pass to consumer.
	 * @param array<string,mixed> $deadletter_options Options to be passed when publishing a message to the deadletter publisher.
	 * @param array<string,mixed> $subscription_options Options to pass when subscribing - only used if consumer is a `SubscriberInterface` instance.
	 * @throws ConnectionException
	 * @throws ConsumeException
	 * @throws PublishException
	 * @throws SubscriptionException
	 * @return void
	 */
	public function listen(
		string|array $location,
		int $max_messages = 1,
		array $options = ["polling_timeout" => 10, "nack_delay" => 10],
		array $deadletter_options = [],
		array $subscription_options = []): void
	{
		$this->listening = true;

		if( $this->consumer instanceof SubscriberInterface ){

			$this->consumer->subscribe($location, $this->middleware, $subscription_options);
			$this->logger?->info(
				"[NIMBLY/SYNDICATE] Subscriber listening started.",
				[
					"subscriber" => $this->consumer::class,
					"topics" => $location,
					"options" => $options,
					"deadletter_options" => $deadletter_options,
				]
			);

			$this->consumer->loop($options);
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
					"options" => $options,
					"deadletter_options" => $deadletter_options,
				]
			);

			/**
			 * @psalm-suppress RedundantCondition
			 */
			while( $this->listening ) {

				$messages = $this->consumer->consume($location, $max_messages, $options);

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

							$this->consumer->nack($message, $options["nack_delay"] ?? 10);
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
								$this->consumer->nack($message, $options["nack_delay"] ?? 10);
								throw new RoutingException(
									"Cannot route message to deadletter as no deadletter implementation ".
									"was given. Either provide a deadletter publisher or add a default ".
									"handler to the router instance."
								);
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