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
	 * Set the routes.
	 *
	 * @param array<string, mixed> $routes
	 * @return void
	 */
	public function setRoutes(array $routes): void
	{
		$this->routes = $routes;
	}

	/**
	 * Add a route handler.
	 *
	 * @param string $key
	 * @param string|array|callable $handler
	 * @return void
	 */
	public function addRoute(string $key, $handler): void
	{
		$this->routes[$key] = $handler;
	}

    /**
     * Match the Message to a route handler.
     *
     * Returns a handler on successful route match.
	 *
	 * Return null if route could not be resolved.
     *
     * @param Message $message
     * @return string|array|callable|null
     */
    public function resolve(Message $message)
    {
        foreach( $this->routes as $key => $handler ){
            if( \call_user_func_array($this->resolver, [$message, $key]) ){
                return $handler;
            }
        }

        return null;
    }
}