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
     * Match the Message to a route callable.
     * 
     * Returns a callable handler on successful route match.
     *
     * @param Message $message
     * @return callable|null
     */
    public function resolve(Message $message): ?callable
    {
        foreach( $this->routes as $route => $handler ){

            if( call_user_func_array($this->resolver, [$message, $route]) ){

                return $handler;

            }
        }

        return null;
    }
}