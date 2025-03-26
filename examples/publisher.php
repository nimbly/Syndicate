<?php

/**
 * This example will publish N messages to a Redis queue on the localhost.
 * By default, N=100, but you can pass a command line argument to set the
 * number of messags to send.
 *
 * Example:
 * `php examples/publisher.php 1200`
 *
 * If you don't have Redis running, you can quickly get an instance
 * up with Docker:
 *
 * `docker run -p 6379:6379 redis:latest`
 *
 * Inentionally, there is one message that is unroutable and should end up
 * in a deadletter state.
 *
 * You can use `examples/consumer.php` to consume the messages published
 * by this script.
 */

use Predis\Client;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Adapter\Redis;
use Nimbly\Syndicate\Filter\ValidatorFilter;
use Nimbly\Syndicate\Validator\JsonSchemaValidator;

require __DIR__ . "/../vendor/autoload.php";

/**
 * Use the fruits.json schema to validate all messages
 * published to the "fruits" topic.
 */
$publisher = new ValidatorFilter(
	validator: new JsonSchemaValidator([
		"fruits" => __DIR__ . "/schemas/fruits.json"
	]),
	publisher: new Redis(new Client)
);

for( $i = 0; $i < ($argv[1] ?? 100); $i++ ){

	$c = \mt_rand(1, 100);

	if( $c <= 5 ){
		// There is no handler defined for this and should end up in the deadletter.
		$fruit = "apples";
	}
	elseif( $c <= 30 ){
		$fruit = "bananas";
	}
	elseif( $c <= 55 ){
		$fruit = "kiwis";
	}
	elseif( $c <= 80 ){
		$fruit = "oranges";
	}
	elseif( $c <= 90) {
		$fruit = "mangoes";
	}
	else {
		$fruit = "mangos";
	}

	$payload = [
		"name" => $fruit,
		"published_at" => \date("c"),
	];

	$publisher->publish(
		new Message("fruits", \json_encode($payload)),
	);
}