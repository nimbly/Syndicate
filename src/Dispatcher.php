<?php

namespace Syndicate;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Syndicate\Message;
use Syndicate\Router;

class Dispatcher
{
	/**
	 * Router instance.
	 *
	 * @var Router
	 */
	protected $router;

	/**
	 * ContainerInteface instance.
	 *
	 * @var ContainerInterface|null
	 */
	protected $container;

	/**
	 * The default handler in case message does not match any routes.
	 *
	 * @var callable|null
	 */
	protected $defaultHandler;

	/**
	 * Dispatcher constructor.
	 *
	 * @param Router $router
	 * @param ContainerInterface $container
	 */
	public function __construct(Router $router, ContainerInterface $container = null)
	{
		$this->router = $router;
		$this->container = $container;
	}

	/**
	 * Set a ContainerInterface instance to be used for dependency resolution.
	 *
	 * @param ContainerInterface $container
	 * @return void
	 */
	public function setContainer(ContainerInterface $container): void
	{
		$this->container = $container;
	}

	/**
	 * Set the default handler.
	 *
	 * @param callable $defaultHandler
	 * @return void
	 */
	public function setDefaultHandler(callable $defaultHandler): void
	{
		$this->defaultHandler = $defaultHandler;
	}

	/**
	 * Dispatch the given Message.
	 *
	 * Throws a DispatchException if no route can be found and no defaultHandler was given.
	 *
	 * @param Message $message
	 * @throws DispatchException
	 * @return void
	 */
	public function dispatch(Message $message): void
	{
		/**
		 * @var string|array|null $handler
		 */
		$handler = $this->router->resolve($message) ?? $this->defaultHandler;

		if( empty($handler) ){
			$dispatchException = new DispatchException("Cannot resolve handler and no default handler defined.");
			$dispatchException->setQueueMessage($message);
			throw $dispatchException;
		}

		$messageHandlers = $this->getCallableHandlers($handler);

		if( empty($messageHandlers) ){
			$dispatchException = new DispatchException("Cannot resolve handlers into callables.");
			$dispatchException->setQueueMessage($message);
			throw $dispatchException;
		}

		foreach( $messageHandlers as $messageHandler ){
			\call_user_func_array(
				$messageHandler,
				$this->resolveDependencies(
					$this->getParametersForCallable($messageHandler),
					[Message::class => $message]
				)
			);
		}
	}

	/**
	 * Try and resolve the handler(s) into an array of callables.
	 *
	 * @param string|array|callable $handlers
	 * @return array<callable>
	 */
	private function getCallableHandlers($handlers): array
	{
		if( !\is_array($handlers) ){
			$handlers = [$handlers];
		}

		/**
		 * @var string|callable $handler
		 */
		return \array_map(function($handler): callable {

			// Handler is already a callable, just return it.
			if( \is_callable($handler) ){
				return $handler;
			}

			// Could be of the format ClassName@MethodName or ClassName::MethodName
			elseif( \is_string($handler) &&
					\preg_match("/^(.+)\@(.+)$/", $handler, $match) ){

				if( \class_exists($match[1]) === false ){
					throw new DispatchException("Class {$match[1]} does not exist.");
				}

				return [$this->make($match[1]), $match[2]];
			}

			throw new DispatchException("Handler could not be resolved into a callable.");

		}, $handlers);
	}

	/**
	 * Get the reflection parameters for a callable.
	 *
	 * @param callable $handler
	 * @return array<ReflectionParameter>
	 */
	private function getParametersForCallable(callable $handler): array
	{
		if( \is_array($handler) ) {
			$reflector = new ReflectionMethod($handler[0], $handler[1]);
		}
		else {
			/**
			 * @psalm-suppress ArgumentTypeCoercion
			 */
			$reflector = new ReflectionFunction($handler);
		}

		return $reflector->getParameters();
	}

	/**
	 * Make an instance of a class using autowiring with values from the container.
	 *
	 * @param class-string $className
	 * @param array<string,mixed> $parameters
	 * @return object
	 */
	private function make(string $className, array $parameters = []): object
	{
		if( $this->container &&
			$this->container->has($className) ){
			return $this->container->get($className);
		}

		$reflectionClass = new ReflectionClass($className);

		if( $reflectionClass->isInterface() || $reflectionClass->isAbstract() ){
			throw new DispatchException("Cannot make an instance of an Interface or Abstract.");
		}

		$constructor = $reflectionClass->getConstructor();

		if( empty($constructor) ){
			return $reflectionClass->newInstance();
		}

		$args = $this->resolveDependencies(
			$constructor->getParameters(),
			$parameters
		);

		return $reflectionClass->newInstanceArgs($args);
	}

	/**
	 * Resolve parameters.
	 *
	 * @param array<ReflectionParameter> $reflectionParameters
	 * @param array<string,mixed> $parameters
	 * @return array
	 */
	private function resolveDependencies(array $reflectionParameters, array $parameters = []): array
	{
		return \array_map(
			/**
			 * @return mixed
			 */
			function(ReflectionParameter $reflectionParameter) use ($parameters) {

				$parameterName = $reflectionParameter->getName();
				$parameterType = $reflectionParameter->getType();

				// No type or the type is a primitive (built in)
				if( empty($parameterType) || $parameterType->isBuiltin() ){

					// Check in user supplied argument list first.
					if( \array_key_exists($parameterName, $parameters) ){
						return $parameters[$parameterName];
					}

					// Does parameter offer a default value?
					elseif( $reflectionParameter->isDefaultValueAvailable() ){
						return $reflectionParameter->getDefaultValue();
					}

					elseif( $reflectionParameter->isOptional() || $reflectionParameter->allowsNull() ){
						return null;
					}
				}

				// Message instance provided by Dispatcher.
				elseif( isset($parameters[Message::class]) &&
					\is_a($parameters[Message::class], $parameterType->getName()) ){
					return $parameters[Message::class];
				}

				// Check container for instance.
				elseif( $this->container && $this->container->has($parameterType->getName()) ){
					return $this->container->get($parameterType->getName());
				}

				// Try to make the instance.
				else {

					/**
					 * @psalm-suppress ArgumentTypeCoercion
					 */
					return $this->make($parameterType->getName(), $parameters);
				}

				throw new DispatchException("Cannot resolve parameter \"{$parameterName}\".");
			},
			$reflectionParameters
		);
	}
}