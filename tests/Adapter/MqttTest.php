<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Closure;
use Mockery;
use Exception;
use Nimbly\Syndicate\Message;
use PhpMqtt\Client\MqttClient;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Adapter\Mqtt;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\SubscriptionException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;

/**
 * @covers Nimbly\Syndicate\Adapter\Mqtt
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

		$message = new Message("mqtt", "Ok", ["qos" => MqttClient::QOS_AT_LEAST_ONCE, "retain" => true]);

		$publisher = new Mqtt($mock);
		$publisher->publish($message);

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

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
		->andReturns(false);

		$mock->shouldReceive("connect");

		$mock->shouldReceive("publish")
		->andThrows(new Exception("Failure"));

		$message = new Message("mqtt", "Ok");

		$publisher = new Mqtt($mock);

		$this->expectException(PublishException::class);
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

	public function test_subscribe_failure_throws_subscription_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false, true);
		$mock->shouldReceive("connect");
		$mock->shouldReceive("subscribe")
			->andThrow(new Exception("Failure"));
		$mock->shouldReceive("disconnect");

		$consumer = new Mqtt($mock);

		$this->expectException(SubscriptionException::class);
		$consumer->subscribe("test", "strtolower");
	}

	public function test_loop_integration_tries_to_connect(): void
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

	public function test_loop_integration_tries_to_disconnect_when_done(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(true);
		$mock->shouldReceive("loop")
			->andReturn(true);
		$mock->shouldReceive("disconnect");

		$consumer = new Mqtt($mock);
		$consumer->loop();

		$mock->shouldHaveReceived("disconnect");
	}

	public function test_disconnect_failure_throws_connection_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(false, true);
		$mock->shouldReceive("disconnect")
			->andThrows(new Exception("Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConnectionException::class);
		$consumer->__destruct();
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
		$mock->shouldReceive("disconnect");

		$consumer = new Mqtt($mock);

		$this->expectException(ConsumeException::class);
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

	public function test_shutdown_general_failure_throws_connection_exception(): void
	{
		$mock = Mockery::mock(MqttClient::class);

		$mock->shouldReceive("isConnected")
			->andReturn(true, true);

		$mock->shouldReceive("disconnect");

		$mock->shouldReceive("interrupt")
			->andThrow(new Exception("Failure"));

		$consumer = new Mqtt($mock);

		$this->expectException(ConnectionException::class);
		$consumer->shutdown();
	}
}