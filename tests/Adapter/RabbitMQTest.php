<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Mockery;
use Exception;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Nimbly\Syndicate\Adapter\RabbitMQ;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RabbitMQ::class)]
class RabbitMQTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration_with_rabbitmq(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_publish")
		->withArgs([AMQPMessage::class, "exch", "rabbitmq", true, true, "tk1"])
		->andReturns((object) ["id" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"]);

		$message = new Message("rabbitmq", "Ok", ["mandatory" => true, "immediate" => true, "ticket" => "tk1"]);

		$publisher = new RabbitMQ($mock);
		$publisher->publish($message, ["exchange" => "exch"]);

		$mock->shouldHaveReceived(
			"basic_publish",
			[AMQPMessage::class, "exch", "rabbitmq", true, true, "tk1"]
			)->once();
	}

	public function test_publish_connection_closed_throws_connection_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_publish")
		->withAnyArgs()
		->andThrows(new AMQPConnectionClosedException("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish(new Message("message", "Ok"));
	}

	public function test_publish_connection_blocked_throws_connection_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_publish")
		->withAnyArgs()
		->andThrows(new AMQPConnectionBlockedException("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish(new Message("message", "Ok"));
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_publish")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(PublishException::class);
		$publisher->publish(new Message("message", "Ok"));
	}

	public function test_consume_integration(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_get")
		->withArgs(["rabbitmq", false, "tck"])
		->andReturns();

		$consumer = new RabbitMQ($mock);
		$consumer->consume("rabbitmq", 1, ["no_ack" => false, "ticket" => "tck"]);

		$mock->shouldHaveReceived(
			"basic_get",
			["rabbitmq", false, "tck"]
			)->once();
	}

	public function test_consume_returns_messages(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$message = new AMQPMessage("Message1");

		$mock->shouldReceive("basic_get")
		->withAnyArgs()
		->andReturns($message);

		$publisher = new RabbitMQ($mock);
		$messages = $publisher->consume("rabbitmq");

		$this->assertCount(1, $messages);

		$this->assertEquals(
			"Message1",
			$messages[0]->getPayload()
		);

		$this->assertSame(
			$message,
			$messages[0]->getReference()
		);
	}

	public function test_consume_connection_closed_throws_connection_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_get")
		->withAnyArgs()
		->andThrows(new AMQPConnectionClosedException("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(ConnectionException::class);
		$publisher->consume("rabbitmq");
	}

	public function test_consume_connection_blocked_throws_connection_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_get")
		->withAnyArgs()
		->andThrows(new AMQPConnectionBlockedException("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(ConnectionException::class);
		$publisher->consume("rabbitmq");
	}

	public function test_consume_failure_throws_consume_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_get")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(ConsumeException::class);
		$publisher->consume("rabbitmq");
	}

	public function test_ack_integration(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$reference = new AMQPMessage("Ok");
		$reference->setChannel($mock);
		$mockReference = Mockery::spy($reference);

		$message = new Message(
			topic: "rabbitmq",
			payload: "Ok",
			reference: $mockReference
		);

		$consumer = new RabbitMQ($mock);
		$consumer->ack($message);

		$mockReference->shouldHaveReceived("ack");
	}

	public function test_ack_connection_closed_throws_connection_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$reference = new AMQPMessage("Ok");
		$reference->setChannel($mock);
		$mockReference = Mockery::spy($reference);

		$mockReference->shouldReceive("ack")
		->withAnyArgs()
		->andThrows(new AMQPConnectionClosedException("Failure"));

		$message = new Message(
			topic: "rabbitmq",
			payload: "Ok",
			reference: $mockReference
		);

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConnectionException::class);
		$consumer->ack($message);
	}

	public function test_ack_connection_blocked_throws_connection_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$reference = new AMQPMessage("Ok");
		$reference->setChannel($mock);
		$mockReference = Mockery::spy($reference);

		$mockReference->shouldReceive("ack")
		->withAnyArgs()
		->andThrows(new AMQPConnectionBlockedException("Failure"));

		$message = new Message(
			topic: "rabbitmq",
			payload: "Ok",
			reference: $mockReference
		);

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConnectionException::class);
		$consumer->ack($message);
	}

	public function test_ack_failure_throws_consume_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$reference = new AMQPMessage("Ok");
		$reference->setChannel($mock);
		$mockReference = Mockery::spy($reference);

		$mockReference->shouldReceive("ack")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message(
			topic: "rabbitmq",
			payload: "Ok",
			reference: $mockReference
		);

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConsumeException::class);
		$consumer->ack($message);
	}

	public function test_nack_integration(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$reference = new AMQPMessage("Ok");
		$reference->setChannel($mock);
		$mockReference = Mockery::spy($reference);

		$message = new Message(
			topic: "rabbitmq",
			payload: "Ok",
			reference: $mockReference
		);

		$consumer = new RabbitMQ($mock);
		$consumer->nack($message);

		$mockReference->shouldHaveReceived("reject", [true]);
	}

	public function test_nack_connection_closed_throws_connection_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$reference = new AMQPMessage("Ok");
		$reference->setChannel($mock);
		$mockReference = Mockery::spy($reference);

		$mockReference->shouldReceive("reject")
		->withAnyArgs()
		->andThrows(new AMQPConnectionBlockedException("Failure"));

		$message = new Message(
			topic: "rabbitmq",
			payload: "Ok",
			reference: $mockReference
		);

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConnectionException::class);
		$consumer->nack($message);
	}

	public function test_nack_failure_throws_consume_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$reference = new AMQPMessage("Ok");
		$reference->setChannel($mock);
		$mockReference = Mockery::spy($reference);

		$mockReference->shouldReceive("reject")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message(
			topic: "rabbitmq",
			payload: "Ok",
			reference: $mockReference
		);

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConsumeException::class);
		$consumer->nack($message);
	}
}