<?php

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
		]
	),
	deadletter: new DeadletterPublisher($client, "deadletter"),
	signals: [SIGINT, SIGHUP, SIGTERM],
	logger: new Logger("EXAMPLE", [new ErrorLogHandler])
);

$application->listen(
	location: "fruits",
	max_messages: 10,
	polling_timeout: 5
);