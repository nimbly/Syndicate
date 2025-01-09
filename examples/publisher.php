<?php

use Predis\Client;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PubSub\Mqtt;
use Nimbly\Syndicate\Queue\Redis;
use Nimbly\Syndicate\PubSub\Redis as PubSubRedis;
use PhpMqtt\Client\MqttClient;

require __DIR__ . "/../vendor/autoload.php";

//$publisher = new PubSubRedis(new Client(options: ["read_write_timeout" => 0]));
$publisher = new Mqtt(new MqttClient("localhost"));

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
		"fruit" => $fruit,
		"published_at" => \date("c"),
	];

	$publisher->publish(
		new Message("fruits", \json_encode($payload)),
	);
}