<?php

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Nimbly\Syndicate\Queue\RabbitMQ;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @covers Nimbly\Syndicate\Queue\RabbitMQ
 */
class RabbitMQTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration_with_rabbitmq(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_publish")
		->withArgs([AMQPMessage::class, "exch", "rabbitmq", true, true, "tk1"])
		->andReturns((object) ["id" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"]);

		$message = new Message("rabbitmq", "Ok");

		$publisher = new RabbitMQ($mock);
		$publisher->publish($message, ["exchange" => "exch", "mandatory" => true, "immediate" => true, "ticket" => "tk1"]);

		$mock->shouldHaveReceived(
			"basic_publish",
			[AMQPMessage::class, "exch", "rabbitmq", true, true, "tk1"]
			)->once();
	}

	public function test_publish_failure_throws_publisher_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_publish")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(PublisherException::class);
		$publisher->publish(new Message("ironmq", "Ok"));
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
		$messages = $publisher->consume("ironmq");

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

	public function test_consume_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(AMQPChannel::class);

		$mock->shouldReceive("basic_get")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$publisher = new RabbitMQ($mock);

		$this->expectException(ConsumerException::class);
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

	public function test_ack_failure_throws_consumer_exception(): void
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

		$this->expectException(ConsumerException::class);
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

	public function test_nack_failure_throws_consumer_exception(): void
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

		$this->expectException(ConsumerException::class);
		$consumer->nack($message);
	}

	public function test_subscribe_integration(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$consumer = new RabbitMQ($mock);
		$consumer->subscribe(
			"rabbitmq",
			"strtolower",
			["consumer_tag" => "ctag", "no_local" => true, "no_ack" => true, "exclusive" => true, "nowait" => true, "ticket" => "tkt"]
		);

		$mock->shouldHaveReceived(
			"basic_consume",
			["rabbitmq", "ctag", true, true, true, true, "strtolower", "tkt"]
		);
	}

	public function test_subscribe_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$mock->shouldReceive("basic_consume")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConsumerException::class);
		$consumer->subscribe("rabbitmq", "strtolower");
	}

	public function test_loop_integration(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$consumer = new RabbitMQ($mock);
		$consumer->loop(["timeout" => 12]);

		$mock->shouldHaveReceived(
			"consume",
			[12.0]
		);
	}

	public function test_loop_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$mock->shouldReceive("consume")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConsumerException::class);
		$consumer->loop();
	}

	public function test_shutdown_integration(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$consumer = new RabbitMQ($mock);
		$consumer->shutdown();

		$mock->shouldHaveReceived("stopConsume");
	}

	public function test_shutdown_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::spy(AMQPChannel::class);

		$mock->shouldReceive("stopConsume")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$consumer = new RabbitMQ($mock);

		$this->expectException(ConsumerException::class);
		$consumer->shutdown();
	}
}