<?php

namespace Nimbly\Syndicate\Tests\Adapter\Queue;

use Mockery;
use Exception;
use IronMQ\IronMQ;
use IronCore\HttpException;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\Queue\Iron;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @covers Nimbly\Syndicate\Adapter\Queue\Iron
 */
class IronMQTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration_with_ironmq(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->shouldReceive("postMessage")
		->withArgs(["ironmq", "Ok", ["opt1" => "val1", "opt2" => "val2"]])
		->andReturns((object) ["id" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"]);

		$message = new Message("ironmq", "Ok");

		$publisher = new Iron($mock);
		$publisher->publish($message, ["opt1" => "val1", "opt2" => "val2"]);

		$mock->shouldHaveReceived(
			"postMessage",
			["ironmq", "Ok", ["opt1" => "val1", "opt2" => "val2"]]
		)->once();
	}

	public function test_publish_returns_receipt(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$message = new Message("ironmq", "Ok");

		$mock->shouldReceive("postMessage")
			->withArgs(["ironmq", "Ok", []])
			->andReturns((object) ["id" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"]);

		/**
		 * @var IronMQ $mock
		 */
		$publisher = new Iron($mock);
		$receipt = $publisher->publish($message);

		$this->assertEquals(
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			$receipt
		);
	}

	public function test_publish_http_failure_throws_connection_exception(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->shouldReceive("postMessage")
		->andThrows(new HttpException("Failure"));

		$message = new Message("ironmq", "Ok");

		$publisher = new Iron($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish($message);
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->shouldReceive("postMessage")
		->andThrows(new Exception("Failure"));

		$message = new Message("ironmq", "Ok");

		$publisher = new Iron($mock);

		$this->expectException(PublishException::class);
		$publisher->publish($message);
	}

	public function test_consume_integration(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->shouldReceive("reserveMessages")
		->withArgs(["ironmq", 10, 15, 20])
		->andReturns([
			(object) [
				"id" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
				"reservation_id" => "0be31d6e-0b46-43d4-854c-772e7d717ce5",
				"body" => "Message1"
			],

			(object) [
				"id" => "022f6eba-d374-4ebf-a8bf-f4faccf95afe",
				"reservation_id" => "596a4980-488f-4e4f-a078-745c3b66cc95",
				"body" => "Message2"
			]
		]);

		$publisher = new Iron($mock);
		$publisher->consume("ironmq", 10, ["timeout" => 15, "wait" => 20]);

		$mock->shouldHaveReceived(
			"reserveMessages",
			["ironmq", 10, 15, 20]
		)->once();
	}

	public function test_consume_returns_messages(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->shouldReceive("reserveMessages")
		->withAnyArgs()
		->andReturns([
			(object) [
				"id" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
				"reservation_id" => "0be31d6e-0b46-43d4-854c-772e7d717ce5",
				"body" => "Message1"
			],

			(object) [
				"id" => "022f6eba-d374-4ebf-a8bf-f4faccf95afe",
				"reservation_id" => "596a4980-488f-4e4f-a078-745c3b66cc95",
				"body" => "Message2"
			]
		]);

		$publisher = new Iron($mock);
		$messages = $publisher->consume("ironmq", 10);

		$this->assertCount(2, $messages);

		$this->assertEquals(
			"Message1",
			$messages[0]->getPayload()
		);

		$this->assertEquals(
			["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"],
			$messages[0]->getReference()
		);

		$this->assertEquals(
			"Message2",
			$messages[1]->getPayload()
		);

		$this->assertEquals(
			["022f6eba-d374-4ebf-a8bf-f4faccf95afe", "596a4980-488f-4e4f-a078-745c3b66cc95"],
			$messages[1]->getReference()
		);
	}

	public function test_consume_http_failure_throws_connection_exception(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->shouldReceive("reserveMessages")
		->andThrows(new HttpException("Failure"));

		$publisher = new Iron($mock);

		$this->expectException(ConnectionException::class);
		$publisher->consume("ironmq");
	}

	public function test_consume_failure_throws_consume_exception(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->shouldReceive("reserveMessages")
		->andThrows(new Exception("Failure"));

		$publisher = new Iron($mock);

		$this->expectException(ConsumeException::class);
		$publisher->consume("ironmq");
	}

	public function test_ack_integration(): void
	{
		$mock = Mockery::spy(IronMQ::class);

		$message = new Message(
			topic: "ironmq",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$consumer = new Iron($mock);
		$consumer->ack($message);

		$mock->shouldHaveReceived(
			"deleteMessage",
			["ironmq", "afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);
	}

	public function test_ack_http_failure_throws_connection_exception(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->expects("deleteMessage")
		->andThrows(new HttpException("Failure"));

		$message = new Message(
			topic: "ironmq",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$consumer = new Iron($mock);

		$this->expectException(ConnectionException::class);
		$consumer->ack($message);
	}

	public function test_ack_failure_throws_consume_exception(): void
	{
		$mock = Mockery::mock(IronMQ::class);

		$mock->expects("deleteMessage")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message(
			topic: "ironmq",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$consumer = new Iron($mock);

		$this->expectException(ConsumeException::class);
		$consumer->ack($message);
	}

	public function test_nack_integration(): void
	{
		$mock = Mockery::spy(IronMQ::class);

		$message = new Message(
			topic: "ironmq",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$consumer = new Iron($mock);
		$consumer->nack($message, 10);

		$mock->shouldHaveReceived(
			"releaseMessage",
			["ironmq", "afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5", 10]
		);
	}

	public function test_nack_http_failure_throws_connection_exception(): void
	{
		$mock = Mockery::spy(IronMQ::class);

		$mock->expects("releaseMessage")
		->andThrows(new HttpException("Failure"));

		$message = new Message(
			topic: "ironmq",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$consumer = new Iron($mock);

		$this->expectException(ConnectionException::class);
		$consumer->nack($message);
	}

	public function test_nack_failure_throws_consume_exception(): void
	{
		$mock = Mockery::spy(IronMQ::class);

		$mock->expects("releaseMessage")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message(
			topic: "ironmq",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$consumer = new Iron($mock);

		$this->expectException(ConsumeException::class);
		$consumer->nack($message);
	}
}