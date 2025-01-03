<?php

namespace Nimbly\Syndicate\Examples;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\RouterInterface;
use Nimbly\Syndicate\RoutingException;

/**
 * An example Router class.
 *
 * This implementation assumes incoming messages have a property called "name"
 * that contains the name of an event. This name property is then used to route
 * the Message to a specific handler.
 *
 * Example message payload:
 *
 * ```json
 * {
 * 		"id": "305d3112-b5ac-4643-921d-22c671b2b5b1",
 * 		"name": "UserRegistered",
 * 		"origin": "user_service.prod.company.com",
 * 		"published_at": "2024-05-12T13:38:02Z",
 * 		"body": {
 * 			"id": "598f38b6-39e1-42ee-a085-e3591a77d6b4",
 * 			"name": "John Doe",
 * 			"email": "john@example.com"
 * 		}
 * }
 * ```
 *
 * The `$routes` array in the constructor could look like this:
 *
 * ```php
 * [
 * 		"UserRegistered" => "App\\Handlers\\UsersHandler@onUserRegistered"
 * ]
 * ```
 * See the README.md for more information.
 */
class Router implements RouterInterface
{
	/**
	 * @param array<string,string|callable> $routes
	 * @param callable|string|null $default
	 */
	public function __construct(
		protected array $routes,
		protected $default = null
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function resolve(Message $message): string|callable|null
	{
		$payload = \json_decode($message->getPayload());

		if( \json_last_error() !== JSON_ERROR_NONE ){
			throw new RoutingException("Could not parse message.");
		}

		return $this->routes[$payload->name] ?? $this->default;
	}
}