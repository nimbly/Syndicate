<?php

namespace Syndicate;

use Syndicate\Message;

class Router
{
    /**
     * Customer route resolver.
     *
     * @var callable
     */
    protected $resolver;

    /**
     * Array of route definitions.
     *
     * @var array
     */
    protected $routes;

    /**
     * Router constructor.
     *
     * @param callable $resolver
     * @param array $routes
     */
    public function __construct(callable $resolver, array $routes = [])
    {
        $this->resolver = $resolver;
        $this->routes = $routes;
    }

    /**
     * Match the Message to a route handler.
     *
     * Returns a handler on successful route match.
     *
     * @param Message $message
     * @return mixed|null
     */
    public function resolve(Message $message)
    {
        foreach( $this->routes as $route => $handler ){

            if( \call_user_func_array($this->resolver, [$message, $route]) ){

                return $handler;

            }
        }

        return null;
    }
}