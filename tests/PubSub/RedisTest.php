<?php

namespace Nimbly\Syndicate\Tests\PubSub;

use Mockery;
use Exception;
use Predis\Client;
use ReflectionClass;
use Predis\PubSub\Consumer;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\PubSub\Redis;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\ConnectionException;
use Predis\Connection\NodeConnectionInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Predis\Connection\ConnectionException as RedisConnectionException;

/**
 * @covers Nimbly\Syndicate\Adapter\PubSub\Redis
 */
class RedisTest extends TestCase
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

	public function test_publish_redis_connection_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("publish")
			->andThrow(
				new RedisConnectionException(
					Mockery::mock(NodeConnectionInterface::class)
				)
			);

		$message = new Message("test", "Ok");

		$publisher = new Redis($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish($message);
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

		$this->assertIsCallable(
			$subscriptions["test"]
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
			["test", "test2"]
		);

		$reflectionClass = new ReflectionClass($consumer);
		$reflectionProperty = $reflectionClass->getProperty("subscriptions");
		$reflectionProperty->setAccessible(true);

		$subscriptions = $reflectionProperty->getValue($consumer);

		$this->assertIsCallable(
			$subscriptions["test"]
		);

		$this->assertIsCallable(
			$subscriptions["test2"]
		);
	}

	public function test_subscribe_redis_connection_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class);

		$mock->shouldReceive("pubSubLoop")
			->andReturns($mockConsumer);

		$mockConsumer->shouldReceive("subscribe")
			->andThrow(
				new RedisConnectionException(
					Mockery::mock(NodeConnectionInterface::class)
				)
			);

		$consumer = new Redis($mock);

		$this->expectException(ConnectionException::class);
		$consumer->subscribe("test", "strtolower");
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
		$mockConsumer->shouldReceive("valid")
			->andReturn(true, false);

		$mockConsumer->shouldReceive("current")
			->andReturn(
				(object) ["kind" => "message", "channel" => "test", "payload" => "Ok"]
			);

		$consumer = new Redis($mock);
		$consumer->subscribe("test", fn($msg) => Response::ack);
		$consumer->loop();

		$mockConsumer->shouldHaveReceived("current");
	}

	public function test_loop_no_callback_for_channel_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
		->andReturn($mockConsumer);

		$mockConsumer->shouldReceive("valid")
			->andReturn(true);

		$mockConsumer->shouldReceive("current")
			->andReturn(
				(object) ["kind" => "message", "channel" => "fruits", "payload" => "Ok"]
			);

		$consumer = new Redis($mock);

		$this->expectException(ConsumerException::class);
		$consumer->loop();
	}

	public function test_loop_redis_connection_exception_throws_connection_exception(): void
	{

		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
			->andReturns($mockConsumer);

		$mockConsumer->shouldReceive("subscribe");
		$mockConsumer->shouldReceive("rewind");
		$mockConsumer->shouldReceive("valid")
	 		->andReturn(true);
		$mockConsumer->shouldReceive("current")
			->andThrow(
				new RedisConnectionException(
					Mockery::mock(NodeConnectionInterface::class)
				)
			);

		$consumer = new Redis($mock);

		$this->expectException(ConnectionException::class);
		$consumer->loop();
	}

	public function test_loop_exception_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
			->andReturns($mockConsumer);

		$mockConsumer->shouldReceive("subscribe");
		$mockConsumer->shouldReceive("rewind");
		$mockConsumer->shouldReceive("valid")
	 		->andReturn(true);
		$mockConsumer->shouldReceive("current")
			->andThrows(new Exception("Failure"));

		$consumer = new Redis($mock);

		$this->expectException(ConsumerException::class);
		$consumer->loop();
	}

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

	public function test_shutdown_redis_connection_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
		->andReturn($mockConsumer);

		$mockConsumer->shouldReceive("stop")
			->andThrow(
				new RedisConnectionException(
					Mockery::mock(NodeConnectionInterface::class)
				)
			);

		$consumer = new Redis($mock);

		$this->expectException(ConnectionException::class);
		$consumer->shutdown();
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