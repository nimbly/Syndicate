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
use Nimbly\Syndicate\Router;
use Nimbly\Syndicate\Application;
use Nimbly\Syndicate\Queue\Redis;
use Monolog\Handler\ErrorLogHandler;
use Nimbly\Syndicate\DeadletterPublisher;
use Nimbly\Syndicate\Examples\Handlers\ExampleHandler;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new Redis(new Client(parameters: ["read_write_timeout" => 0]));

$application = new Application(
	consumer: $client,
	router: new Router(
		handlers: [
			ExampleHandler::class,
		],
	),
	deadletter: new DeadletterPublisher($client, "deadletter"),
	logger: new Logger("EXAMPLE", [new ErrorLogHandler]),
);

$application->listen(
	location: "fruits",
);