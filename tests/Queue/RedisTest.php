<?php

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\Queue\Redis;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * @covers Nimbly\Syndicate\Queue\Redis
 */
class RedisTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("rpush")
		->withAnyArgs()
		->andReturns(123);

		$message = new Message("redis", "Ok");

		$redis = new Redis($mock);
		$receipt = $redis->publish($message);

		$this->assertEquals(
			"123",
			$receipt
		);

		$mock->shouldHaveReceived(
			"rpush",
			["redis", ["Ok"]]
		);
	}

	public function test_publish_returns_receipt(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("rpush")
		->withArgs(["redis", ["Ok"]])
		->andReturns(123);

		$message = new Message("redis", "Ok");

		$redis = new Redis($mock);
		$receipt = $redis->publish($message);

		$this->assertEquals(
			"123",
			$receipt
		);
	}

	public function test_publish_failure_throws_publisher_exception(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("rpush")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message("redis", "Ok");

		$redis = new Redis($mock);

		$this->expectException(PublisherException::class);
		$redis->publish($message);
	}

	public function test_consume_integration(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("lpop")
		->withAnyArgs()
		->andReturns([]);

		$redis = new Redis($mock);
		$redis->consume("redis", 10);

		$mock->shouldHaveReceived(
			"lpop",
			["redis", 10]
		);
	}

	public function test_consume(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("lpop")
		->withAnyArgs()
		->andReturns(["Message1", "Message2"]);

		$redis = new Redis($mock);
		$messages = $redis->consume("redis", 10);

		$this->assertCount(2, $messages);

		$this->assertEquals(
			"redis",
			$messages[0]->getTopic()
		);

		$this->assertEquals(
			"Message1",
			$messages[0]->getPayload()
		);

		$this->assertEquals(
			"redis",
			$messages[1]->getTopic()
		);

		$this->assertEquals(
			"Message2",
			$messages[1]->getPayload()
		);
	}

	public function test_consume_no_messages_returns_empty_array(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("lpop")
		->withAnyArgs()
		->andReturnNull();

		$redis = new Redis($mock);
		$messages = $redis->consume("redis", 10);

		$this->assertCount(0, $messages);
	}

	public function test_consume_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("lpop")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$redis = new Redis($mock);

		$this->expectException(ConsumerException::class);
		$redis->consume("redis");
	}

	public function test_nack(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("rpush")
		->withAnyArgs()
		->andReturns(123);

		$message = new Message("redis", "Message1");

		$redis = new Redis($mock);
		$redis->nack($message);

		$mock->shouldHaveReceived(
			"rpush",
			["redis", ["Message1"]]
		);
	}

	public function test_nack_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(Client::class);

		$mock->shouldReceive("rpush")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message("redis", "Message1");

		$redis = new Redis($mock);

		$this->expectException(ConsumerException::class);
		$redis->nack($message);
	}
}