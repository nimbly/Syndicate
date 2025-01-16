<?php

use Nimbly\Syndicate\Message;
use PhpMqtt\Client\MqttClient;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\PubSub\Mqtt;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nimbly\Syndicate\ConnectionException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;

/**
 * @covers Nimbly\Syndicate\PubSub\Mqtt
 */
class MqttTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
		->andReturns(false);

		$mock->shouldReceive("connect");
		$mock->shouldReceive("publish");

		$message = new Message("mqtt", "Ok");

		$publisher = new Mqtt($mock);
		$publisher->publish($message, ["qos" => MqttClient::QOS_AT_LEAST_ONCE, "retain" => true]);

		$mock->shouldHaveReceived("isConnected");
		$mock->shouldHaveReceived("connect");
		$mock->shouldHaveReceived(
			"publish",
			["mqtt", "Ok", MqttClient::QOS_AT_LEAST_ONCE, true]
		);
	}

	public function test_connect_failure_throws_connection_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
		->andReturns(false);

		$mock->shouldReceive("connect")
		->andThrows(new Exception("Failure"));

		$message = new Message("mqtt", "Ok");

		$publisher = new Mqtt($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish($message);
	}

	public function test_publish_connecting_to_broker_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
		->andReturns(false);

		$mock->shouldReceive("connect");

		$mock->shouldReceive("publish")
		->andThrows(new ConnectingToBrokerFailedException(0, "Failure"));

		$message = new Message("mqtt", "Ok");

		$publisher = new Mqtt($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish($message);
	}

	public function test_publish_failure_throws_publisher_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
		->andReturns(false);

		$mock->shouldReceive("connect");

		$mock->shouldReceive("publish")
		->andThrows(new Exception("Failure"));

		$message = new Message("mqtt", "Ok");

		$publisher = new Mqtt($mock);

		$this->expectException(PublisherException::class);
		$publisher->publish($message);
	}

	public function test_subscribe_integration(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("subscribe");

		$consumer = new Mqtt($mock);
		$consumer->subscribe("test", "strtolower", ["qos" => 12]);

		$mock->shouldHaveReceived("isConnected");
		$mock->shouldHaveReceived("connect");
		$mock->shouldHaveReceived(
			"subscribe",
			["test", Closure::class, 12]
		);
	}

	public function test_multi_subscribe_integration(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("subscribe")
			->twice();

		$consumer = new Mqtt($mock);
		$consumer->subscribe(["test", "test2"], "strtolower");

		$mock->shouldHaveReceived("isConnected");
		$mock->shouldHaveReceived("connect");

		$mock->shouldHaveReceived(
			"subscribe",
			["test", Closure::class, MqttClient::QOS_AT_MOST_ONCE]
		);

		$mock->shouldHaveReceived(
			"subscribe",
			["test2", Closure::class, MqttClient::QOS_AT_MOST_ONCE]
		);
	}

	public function test_subscribe_connecting_to_broker_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("subscribe")
			->andThrow(new ConnectingToBrokerFailedException(0, "Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConnectionException::class);
		$consumer->subscribe("test", "strtolower");
	}

	public function test_subscribe_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("subscribe")
			->andThrow(new Exception("Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConsumerException::class);
		$consumer->subscribe("test", "strtolower");
	}

	public function test_loop_integration(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("loop");

		$consumer = new Mqtt($mock);
		$consumer->loop(["allow_sleep" => false, "exit_when_empty" => true, "timeout" => 12]);

		$mock->shouldHaveReceived("isConnected");
		$mock->shouldHaveReceived("connect");
		$mock->shouldHaveReceived(
			"loop",
			[false, true, 12]
		);
	}

	public function test_loop_connecting_to_broker_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("loop")
			->andThrow(new ConnectingToBrokerFailedException(0, "Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConnectionException::class);
		$consumer->loop();
	}

	public function test_loop_failure_throws_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("loop")
			->andThrow(new Exception("Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConsumerException::class);
		$consumer->loop();
	}

	public function test_shutdown_integration(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(true);

		$mock->shouldReceive("interrupt");
		$mock->shouldReceive("disconnect");

		$consumer = new Mqtt($mock);
		$consumer->shutdown();

		$mock->shouldHaveReceived("interrupt");
	}

	public function test_shutdown_connecting_to_broker_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(true);

		$mock->shouldReceive("disconnect");

		$mock->shouldReceive("interrupt")
			->andThrow(new ConnectingToBrokerFailedException(0, "Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConnectionException::class);
		$consumer->shutdown();
	}

	public function test_shutdown_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(true);

		$mock->shouldReceive("disconnect");

		$mock->shouldReceive("interrupt")
			->andThrow(new Exception("Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConsumerException::class);
		$consumer->shutdown();
	}
}