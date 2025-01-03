<?php

use Predis\Client;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Queue\Redis;

require __DIR__ . "/../vendor/autoload.php";

$publisher = new Redis(new Client(options: ["read_write_timeout" => 0]));

for( $i = 0; $i < ($argv[1] ?? 100); $i++ ){

	if( \mt_rand(1, 100) <= 5 ){
		$fruit = "apples"; // un-routable
	}
	else {
		$c = \mt_rand(1, 100);

		if( $c <= 25 ){
			$fruit = "bananas";
		}
		elseif( $c <= 50 ){
			$fruit = "kiwis";
		}
		elseif( $c <= 75 ){
			$fruit = "oranges";
		}
		else {
			$fruit = "mangoes";
		}
	}

	$payload = [
		"name" => $fruit,
		"published_at" => \date("c"),
	];

	$publisher->publish(
		new Message("fruits", \json_encode($payload))
	);
}
