<?php

use Predis\Client;
use Nimbly\Syndicate\Response;
use Nimbly\Syndicate\Application;
use Nimbly\Syndicate\Queue\Redis;
use Nimbly\Syndicate\Examples\Router;
use Nimbly\Syndicate\DeadletterPublisher;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new Redis(new Client(options: ["read_write_timeout" => 0]));

$application = new Application(
	consumer: $client,
	router: new Router(
		routes: [
			"bananas" => "Nimbly\\Syndicate\\Examples\\Handlers\\ExampleHandler@onBananas",
			"kiwis" => "Nimbly\\Syndicate\\Examples\\Handlers\\ExampleHandler@onKiwis",
			"oranges" => "Nimbly\\Syndicate\\Examples\\Handlers\\ExampleHandler@onOranges",
			"mangoes" => "Nimbly\\Syndicate\\Examples\\Handlers\\ExampleHandler@onMangoes",
		],
		default: function(): Response {
			echo "[ERROR] Could not resolve message into a routable handler.\n";
			return Response::deadleter;
		}
	),
	deadletter: new DeadletterPublisher($client, "deadletter"),
	signals: [SIGINT, SIGHUP, SIGTERM],
);

$application->listen(
	location: "fruits",
	max_messages: 10,
	polling_timeout: 5
);