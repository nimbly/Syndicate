<?php

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nimbly\Syndicate\PubSub\Redis;
use Predis\Client;
use Predis\PubSub\Consumer;

/**
 * @covers Nimbly\Syndicate\PubSub\Redis
 */
class RedisPubSubTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("publish");

		$message = new Message("test", "Ok");

		$publisher = new Redis($mock);
		$publisher->publish($message);

		$mock->shouldHaveReceived(
			"publish",
			["test", "Ok"]
		);
	}

	public function test_publish_returns_receipt(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("publish")
			->andReturn(123);

		$message = new Message("test", "Ok");

		$publisher = new Redis($mock);
		$receipt = $publisher->publish($message);

		$this->assertEquals(
			"123",
			$receipt
		);
	}

	public function test_publish_failure_throws_publisher_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("publish")
			->andThrow(new Exception("Failure"));

		$message = new Message("test", "Ok");

		$publisher = new Redis($mock);

		$this->expectException(PublisherException::class);
		$publisher->publish($message);
	}

	public function test_subscribe_integration(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class);

		$mock->shouldReceive("pubSubLoop")
			->andReturn($mockConsumer);
		$mockConsumer->shouldReceive("subscribe");

		$consumer = new Redis($mock);
		$consumer->subscribe("test", "strtolower");

		$mock->shouldHaveReceived("pubSubLoop");
		$mockConsumer->shouldHaveReceived(
			"subscribe",
			["test"]
		);

		$reflectionClass = new ReflectionClass($consumer);
		$reflectionProperty = $reflectionClass->getProperty("subscriptions");
		$reflectionProperty->setAccessible(true);

		$subscriptions = $reflectionProperty->getValue($consumer);

		$this->assertEquals(
			["test" => "strtolower"],
			$subscriptions
		);
	}

	public function test_multi_subscribe_integration(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class);

		$mock->shouldReceive("pubSubLoop")
			->andReturn($mockConsumer);
		$mockConsumer->shouldReceive("subscribe");

		$consumer = new Redis($mock);
		$consumer->subscribe(["test", "test2"], "strtolower");

		$mock->shouldHaveReceived("pubSubLoop");

		$mockConsumer->shouldHaveReceived(
			"subscribe",
			["test"]
		);

		$mockConsumer->shouldHaveReceived(
			"subscribe",
			["test2"]
		);

		$reflectionClass = new ReflectionClass($consumer);
		$reflectionProperty = $reflectionClass->getProperty("subscriptions");
		$reflectionProperty->setAccessible(true);

		$subscriptions = $reflectionProperty->getValue($consumer);

		$this->assertEquals(
			["test" => "strtolower", "test2" => "strtolower"],
			$subscriptions
		);
	}

	public function test_subscribe_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class);

		$mock->shouldReceive("pubSubLoop")
			->andReturns($mockConsumer);

			$mockConsumer->shouldReceive("subscribe")
			->withAnyArgs()
			->andThrow(new Exception("Failure"));

		$consumer = new Redis($mock);

		$this->expectException(ConsumerException::class);
		$consumer->subscribe("test", "strtolower");
	}

	public function test_loop_integration(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
		->andReturn($mockConsumer);

		$mockConsumer->shouldReceive("subscribe");
		$mockConsumer->shouldReceive("rewind");
		$mockConsumer->shouldReceive("valid");
		$mockConsumer->shouldReceive("getValue")
		->andReturn([
			(object) ["message", "test", "Ok"]
		]);

		$consumer = new Redis($mock);
		$consumer->subscribe("test", fn($msg) => $msg->payload);
		$consumer->loop();

		$mockConsumer->shouldHaveReceived("subscribe");
		$mockConsumer->shouldHaveReceived("rewind");
		$mockConsumer->shouldHaveReceived("valid");
		//$mockConsumer->shouldHaveReceived("getValue");
	}

	// public function test_loop_failure_throws_exception(): void
	// {
	// 	$mock = Mockery::mock(Client::class);
	// 	$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

	// 	$mock->shouldReceive("pubSubLoop")
	// 		->andReturns($mockConsumer);

	// 	$mockConsumer->shouldReceive("subscribe");
	// 	$mockConsumer->shouldReceive("rewind");
	// 	$mockConsumer->shouldReceive("valid");
	// 	$mockConsumer->shouldReceive("getValue")
	// 		->andThrows(new Exception("Failure"));

	// 	$consumer = new Redis($mock);

	// 	$this->expectException(ConsumerException::class);
	// 	$consumer->loop();
	// }

	public function test_shutdown_integration(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
		->andReturn($mockConsumer);

		$mockConsumer->shouldReceive("stop");

		$consumer = new Redis($mock);
		$consumer->shutdown();

		$mockConsumer->shouldHaveReceived("stop", [true]);
	}

	public function test_shutdown_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
		->andReturn($mockConsumer);

		$mockConsumer->shouldReceive("stop")
			->andThrow(new Exception("Failure"));

		$consumer = new Redis($mock);

		$this->expectException(ConsumerException::class);
		$consumer->shutdown();
	}
}