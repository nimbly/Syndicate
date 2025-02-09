<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Mockery;
use Exception;
use Predis\Client;
use ReflectionClass;
use Predis\PubSub\Consumer;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\SubscriptionException;
use Nimbly\Syndicate\Adapter\RedisPubSub;
use Predis\Connection\NodeConnectionInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Predis\Connection\ConnectionException as RedisConnectionException;

/**
 * @covers Nimbly\Syndicate\Adapter\RedisPubSub
 */
class RedisPubSubTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("publish");

		$message = new Message("test", "Ok");

		$publisher = new RedisPubSub($mock);
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

		$publisher = new RedisPubSub($mock);
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

		$publisher = new RedisPubSub($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish($message);
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("publish")
			->andThrow(new Exception("Failure"));

		$message = new Message("test", "Ok");

		$publisher = new RedisPubSub($mock);

		$this->expectException(PublishException::class);
		$publisher->publish($message);
	}

	public function test_subscribe_integration(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class);

		$mock->shouldReceive("pubSubLoop")
			->andReturn($mockConsumer);
		$mockConsumer->shouldReceive("subscribe");

		$consumer = new RedisPubSub($mock);
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

		$consumer = new RedisPubSub($mock);
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

		$consumer = new RedisPubSub($mock);

		$this->expectException(ConnectionException::class);
		$consumer->subscribe("test", "strtolower");
	}

	public function test_subscribe_failure_throws_consume_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class);

		$mock->shouldReceive("pubSubLoop")
			->andReturns($mockConsumer);

			$mockConsumer->shouldReceive("subscribe")
			->withAnyArgs()
			->andThrow(new Exception("Failure"));

		$consumer = new RedisPubSub($mock);

		$this->expectException(SubscriptionException::class);
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

		$consumer = new RedisPubSub($mock);
		$consumer->subscribe("test", fn($msg) => Response::ack);
		$consumer->loop();

		$mockConsumer->shouldHaveReceived("current");
	}

	public function test_loop_no_callback_for_channel_throws_consume_exception(): void
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

		$consumer = new RedisPubSub($mock);

		$this->expectException(ConsumeException::class);
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

		$consumer = new RedisPubSub($mock);

		$this->expectException(ConnectionException::class);
		$consumer->loop();
	}

	public function test_loop_exception_throws_consume_exception(): void
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

		$consumer = new RedisPubSub($mock);

		$this->expectException(ConsumeException::class);
		$consumer->loop();
	}

	public function test_shutdown_integration(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
		->andReturn($mockConsumer);

		$mockConsumer->shouldReceive("stop");

		$consumer = new RedisPubSub($mock);
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

		$consumer = new RedisPubSub($mock);

		$this->expectException(ConnectionException::class);
		$consumer->shutdown();
	}

	public function test_shutdown_failure_throws_connection_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mockConsumer = Mockery::mock(Consumer::class)->shouldAllowMockingProtectedMethods();

		$mock->shouldReceive("pubSubLoop")
		->andReturn($mockConsumer);

		$mockConsumer->shouldReceive("stop")
			->andThrow(new Exception("Failure"));

		$consumer = new RedisPubSub($mock);

		$this->expectException(ConnectionException::class);
		$consumer->shutdown();
	}

	public function test_get_loop_failure_throws_consume_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("pubSubLoop")
			->andReturn(null);

		$consumer = new RedisPubSub($mock);

		$reflectionClass = new ReflectionClass($consumer);
		$reflectionMethod = $reflectionClass->getMethod("getLoop");
		$reflectionMethod->setAccessible(true);

		$this->expectException(ConsumeException::class);
		$reflectionMethod->invoke($consumer);
	}
}