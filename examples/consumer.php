<?php

/**
 * This example sets up a consumer listening on a Redis queue on
 * your localhost and using the `Handlers\ExampleHandler.php` as
 * message handlers.
 *
 * If you don't have Redis running, you can quickly get an instance
 * up with Docker:
 *
 * `docker run -p 6379:6379 redis:latest`
 *
 * You can send this consumer example messages by running the
 * `examples/publisher.php` script.
 *
 * To exit, just hit Ctrl-C.
 */

use Predis\Client;
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use Nimbly\Syndicate\Application;
use Nimbly\Syndicate\Router\Router;
use Nimbly\Syndicate\Adapter\Redis;
use Nimbly\Syndicate\Filter\RedirectFilter;
use Nimbly\Syndicate\Middleware\ValidateMessage;
use Nimbly\Syndicate\Validator\JsonSchemaValidator;
use Nimbly\Syndicate\Examples\Handlers\ExampleHandler;
use Nimbly\Syndicate\Middleware\ParseJsonMessage;

require_once __DIR__ . "/../vendor/autoload.php";

/**
 * Use a Redis queue as our data source.
 */
$client = new Redis(new Client(parameters: ["read_write_timeout" => 0]));

/**
 * Validate messages against the "fruits" topic JSON schema.
 */
$validator = new JsonSchemaValidator(
	["fruits" => \file_get_contents(__DIR__ . "/schemas/fruits.json")]
);

$application = new Application(
	consumer: $client,

	/**
	 * Create a Router instance with our single ExampleHandler class.
	 */
	router: new Router(
		handlers: [
			ExampleHandler::class,
		],
	),

	/**
	 * Redirect deadletter messages back to the same Redis queue
	 * except publish them to the "deadletter" queue.
	 */
	deadletter: new RedirectFilter($client, "deadletter"),

	/**
	 * Add a simple logger to show what's going on behind the scenes.
	 */
	logger: new Logger("EXAMPLE", [new ErrorLogHandler]),

	middleware: [

		/**
		 * Parse all incoming messages as JSON.
		 */
		new ParseJsonMessage,

		/**
		 * Validate all incoming messages against our JSON schema.
		 */
		new ValidateMessage($validator)
	]
);

$application->listen(location: "fruits");