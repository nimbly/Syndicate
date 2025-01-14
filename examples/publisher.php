<?php

use Predis\Client;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Queue\Redis;

require __DIR__ . "/../vendor/autoload.php";

$publisher = new Redis(new Client(options: ["read_write_timeout" => 0]));

for( $i = 0; $i < ($argv[1] ?? 100); $i++ ){

	$c = \mt_rand(1, 100);

	if( $c <= 5 ){
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
	else {
		$fruit = "mangoes";
	}

	$payload = [
		"name" => $fruit,
		"published_at" => \date("c"),
	];

	$publisher->publish(
		new Message("fruits", \json_encode($payload)),
	);
}