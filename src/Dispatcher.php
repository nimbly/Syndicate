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
     * @var callable
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
     * @param Queue $queue
     * @return void
     */
    public function listen(Queue $queue): void
    {
        $queue->listen(function(Message $message) {

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
     * @return mixed
     */
    public function dispatch(Message $message)
    {
        if( ($handler = $this->router->resolve($message)) === null ){
            
            if( !$this->defaultHandler ){
                throw new \Exception("Cannot resolve a route for message and no defaultHandler defined.");
            }

            $handler = $this->defaultHandler;
        }

        $handler = $this->resolveHandler($handler);

        if( empty($handler) ){
            throw new \Exception("Invalid handler.");
        }

        return call_user_func($handler, $message);
    }

    /**
     * Try and resolve the handler type into a callable.
     *
     * @param mixed $handler
     * @return callable|null
     */
    private function resolveHandler($handler): ?callable
    {
        if( is_callable($handler) ){
            return $handler;
        }

        if( is_array($handler) &&
            count($handler) == 2 &&
            is_string($handler[0]) &&
            is_string($handler[1]) ){

            // Look in cache for an instance before creating a new one.
            if( ($instance = $this->getFromHandlerCache($handler[0])) == false ){
                $instance = new $handler[0];
                $this->putInHandlerCache($handler[0], $instance);
            }

            return [$instance, $handler[1]];
        }

        // Could be of the format ClassName@MethodName or ClassName::MethodName
        elseif( is_string($handler) ){

            if( preg_match("/^(.+)(?:(\@|\:\:))(.+)$/", $handler, $match) ){

                if( ($instance = $this->getFromHandlerCache($match[1])) == false ){
                    $instance = new $match[1];
                    $this->putInHandlerCache($match[1], $instance);
                }

                return [$instance, $match[2]];
            }
        }

        return null;
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