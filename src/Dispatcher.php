<?php

namespace Syndicate;

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
     * The default handler in case message does not match any routes.
     *
     * @var ?callable
     */
    protected $defaultHandler;

    /**
     * Instances of handler cache.
     *
     * @var array<string, object>
     */
    private $handlerCache = [];

    /**
     * Dispatcher constructor.
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
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
        /** @var string|array|null $handler */
        $handler = $this->router->resolve($message);

        if( empty($handler) ){
			$handler = $this->defaultHandler;

			if( empty($handler) ){
				$dispatchException = new DispatchException("Cannot resolve handler and no default handler defined.");
				$dispatchException->setQueueMessage($message);
				throw $dispatchException;
			}
        }

        $callableHandlers = $this->makeHandlersCallable($handler);

        if( empty($callableHandlers) ){
			$dispatchException = new DispatchException("Cannot resolve handlers into callables.");
			$dispatchException->setQueueMessage($message);
			throw $dispatchException;
        }

        foreach( $callableHandlers as $callableHandler ){
            \call_user_func($callableHandler, $message);
        }
    }

    /**
     * Try and resolve the handler(s) into an array of callables.
     *
     * @param string|array|callable $handlers
     * @return array<callable>
     */
    private function makeHandlersCallable($handlers): array
    {
        if( !\is_array($handlers) ){
            $handlers = [$handlers];
        }

        /**
		 * @var array $callables
		 * @psalm-suppress MissingClosureParamType
		 */
        $callables = \array_reduce($handlers, function(array $callables, $handler): array {

            if( \is_callable($handler) ){
                $callables[] = $handler;
            }

            elseif( \is_string($handler) ){

                // Could be of the format ClassName@MethodName or ClassName::MethodName
                if( \preg_match("/^(.+)\@(.+)$/", $handler, $match) ){

                    if( ($instance = $this->getFromHandlerCache($match[1])) == false ){

                        /**
                         * @psalm-suppress InvalidStringClass
                         */
                        $instance = new $match[1];
                        $this->putInHandlerCache($match[1], $instance);
                    }

                    $callables[] = [$instance, $match[2]];
                }
            }

            return $callables;

        }, []);

        return $callables;
    }

    /**
     * Get callable handler from cache.
     *
     * @param string $key
     * @return object|null
     */
    private function getFromHandlerCache(string $key): ?object
    {
        return $this->handlerCache[$key] ?? null;
    }

    /**
     * Put a callable handler in to the cache.
     *
     * @param string $key
     * @param object $handler
     * @return void
     */
    private function putInHandlerCache(string $key, object $handler): void
    {
        $this->handlerCache[$key] = $handler;
    }
}