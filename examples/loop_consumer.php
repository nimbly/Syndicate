<?php

use Predis\Client;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PubSub\Redis as PubSubRedis;

require_once __DIR__ . "/../vendor/autoload.php";

$consumer = new PubSubRedis(new Client(parameters: ["read_write_timeout" => 0]));

/**
 * Using a class and public method as a handler.
 */
class FruitsHandler
{
	public function onFruitReceived(Message $message): void
	{
		\error_log(\sprintf("[%s] Received %s", $message->getTopic(), $message->getPayload()));
	}

	public function onFruitRipened(Message $message): void
	{
		\error_log(\sprintf("[%s] Received %s", $message->getTopic(), $message->getPayload()));
	}
}


$consumer->subscribe("fruits", "FruitsHandler@onFruitReceived");
$consumer->subscribe("ripened", "FruitsHandler@onFruitRipened");
$consumer->loop();
exit;

/**
 * Using a closure as a callback.
 */
$consumer->subscribe("fruits", function(Message $message){
	echo \sprintf("[%s] Received %s", $message->getTopic(), $message->getPayload());
});

$consumer->subscribe("ripened", function(Message $message){
	echo \sprintf("[%s] Received %s", $message->getTopic(), $message->getPayload());
});

$consumer->loop();
exit;