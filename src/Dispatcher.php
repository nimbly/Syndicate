<?php

namespace Syndicate;

use Syndicate\Message;
use Syndicate\Queue\Queue;
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
     * Listen on a Queue for new messages to be dispatched.
     * 
     * This is a blocking call.
     *
     * @param Queue $queue
     * @return void
     */
    public function listen(Queue $queue): void
    {
        $queue->listen(function(Message $message): void {

            $this->dispatch($message);
            
        });
    }

    /**
     * Dispatch the given Message.
     * 
     * Throws an exception if no route can be found and no defaultHandler was given.
     *
     * @param Message $message
     * @throws \Exception
     * @return void
     */
    public function dispatch(Message $message): void
    {
        // No handler could be resolved, fallback to defaultHandler.
        if( ($handler = $this->router->resolve($message)) === null ){
            $handler = $this->defaultHandler;
        }

        $handlers = $this->resolveHandlers($handler);

        if( empty($handlers) ){
            throw new \Exception("Cannot resolve handler.");
        }

        foreach( $handlers as $handler ){
            \call_user_func($handler, $message);
        }
    }

    /**
     * Try and resolve the handler type into a callable.
     *
     * @param string|array|callable $handlers
     * @return array<callable>
     */
    private function resolveHandlers($handlers): array
    {
        if( !\is_array($handlers) ){
            $handlers = [$handlers];
        }

        $callables = [];

        foreach( $handlers as $handler ){

            if( \is_callable($handler) ){
                $callables[] = $handler;
            }
    
            // Could be of the format ClassName@MethodName or ClassName::MethodName
            if( \is_string($handler) ){
    
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

        }

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