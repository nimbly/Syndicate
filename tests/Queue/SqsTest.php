<?php

use Aws\Result;
use Aws\Sqs\SqsClient;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nimbly\Syndicate\Queue\Sqs;

/**
 * @covers Nimbly\Syndicate\Queue\Sqs
 */
class SqsTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration(): void
	{
		$mock = Mockery::mock(SqsClient::class);

		$mock->shouldReceive("sendMessage")
		->withAnyArgs()
		->andReturns(new Result([
			"MessageId" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"
		]));

		$message = new Message("queue_url", "Ok", ["attr1" => "val1", "attr2" => "val2"]);

		$sqs = new Sqs($mock);
		$sqs->publish($message, ["opt1" => "val1", "opt2" => "val2"]);

		$mock->shouldHaveReceived(
			"sendMessage",
			[
				[
					"QueueUrl" => "queue_url",
					"MessageBody" => "Ok",
					"MessageAttributes" => ["attr1" => "val1", "attr2" => "val2"],
					"opt1" => "val1",
					"opt2" => "val2"
				]
			]
		);
	}

	public function test_publish_returns_receipt(): void
	{
		$mock = Mockery::mock(SqsClient::class);

		$mock->shouldReceive("sendMessage")
		->withAnyArgs()
		->andReturns(new Result([
			"MessageId" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"
		]));

		$message = new Message("queue_url", "Ok", ["attr1" => "val1", "attr2" => "val2"]);

		$sqs = new Sqs($mock);
		$receipt = $sqs->publish($message, ["opt1" => "val1", "opt2" => "val2"]);

		$this->assertEquals(
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			$receipt
		);
	}

	public function test_publish_failure_throws_publisher_exception(): void
	{
		$mock = Mockery::mock(SqsClient::class);

		$mock->shouldReceive("sendMessage")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message("queue_url", "Ok");

		$sqs = new Sqs($mock);

		$this->expectException(PublisherException::class);
		$sqs->publish($message);
	}

	public function test_consume_integration(): void
	{
		$mock = Mockery::mock(SqsClient::class);

		$mock->shouldReceive("receiveMessage")
		->withAnyArgs()
		->andReturns(new Result([
			"Messages" => [
				[
					"Body" => "Message1",
					"ReceiptHandle" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"
				],

				[
					"Body" => "Message2",
					"ReceiptHandle" => "0be31d6e-0b46-43d4-854c-772e7d717ce5"
				],
			]
		]));

		$sqs = new Sqs($mock);
		$sqs->consume("queue_url", 10, ["opt1" => "val1", "opt2" => "val2"]);

		$mock->shouldHaveReceived(
			"receiveMessage",
			[
				[
					"QueueUrl" => "queue_url",
					"MaxNumberOfMessages" => 10,
					"opt1" => "val1",
					"opt2" => "val2"
				]
			]
		);
	}

	public function test_consume(): void
	{
		$mock = Mockery::mock(SqsClient::class);

		$mock->shouldReceive("receiveMessage")
		->withAnyArgs()
		->andReturns(new Result([
			"Messages" => [
				[
					"Body" => "Message1",
					"ReceiptHandle" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"
				],

				[
					"Body" => "Message2",
					"ReceiptHandle" => "0be31d6e-0b46-43d4-854c-772e7d717ce5"
				],
			]
		]));

		$sqs = new Sqs($mock);
		$messages = $sqs->consume("queue_url", 10);

		$this->assertCount(2, $messages);

		$this->assertEquals(
			"queue_url",
			$messages[0]->getTopic()
		);

		$this->assertEquals(
			"Message1",
			$messages[0]->getPayload()
		);

		$this->assertEquals(
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			$messages[0]->getReference()
		);

		$this->assertEquals(
			"queue_url",
			$messages[1]->getTopic()
		);

		$this->assertEquals(
			"Message2",
			$messages[1]->getPayload()
		);

		$this->assertEquals(
			"0be31d6e-0b46-43d4-854c-772e7d717ce5",
			$messages[1]->getReference()
		);
	}

	public function test_consume_failure_throws_exception(): void
	{
		$mock = Mockery::mock(SqsClient::class);

		$mock->shouldReceive("receiveMessage")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$sqs = new Sqs($mock);

		$this->expectException(ConsumerException::class);
		$sqs->consume("queue_url", 10);
	}

	public function test_ack_integration(): void
	{
		$mock = Mockery::spy(SqsClient::class);

		$message = new Message("queue_url", "Message1", [], [], "afd1cbe8-6ee3-4de0-90f5-50c019a9a887");

		$sqs = new Sqs($mock);
		$sqs->ack($message);

		$mock->shouldHaveReceived(
			"deleteMessage",
			[
				[
					"QueueUrl" => "queue_url",
					"ReceiptHandle" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"
				]
			]
		);
	}

	public function test_ack_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(SqsClient::class);
		$mock->shouldReceive("deleteMessage")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message("queue_url", "Message1", [], [], "afd1cbe8-6ee3-4de0-90f5-50c019a9a887");

		$sqs = new Sqs($mock);

		$this->expectException(ConsumerException::class);
		$sqs->ack($message);
	}

	public function test_nack_integration(): void
	{
		$mock = Mockery::spy(SqsClient::class);

		$message = new Message("queue_url", "Message1", [], [], "afd1cbe8-6ee3-4de0-90f5-50c019a9a887");

		$sqs = new Sqs($mock);
		$sqs->nack($message, 10);

		$mock->shouldHaveReceived(
			"changeMessageVisibility",
			[
				[
					"QueueUrl" => "queue_url",
					"ReceiptHandle" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
					"VisibilityTimeout" => 10
				]
			]
		);
	}

	public function test_nack_failure_throws_consumer_exception(): void
	{
		$mock = Mockery::mock(SqsClient::class);
		$mock->shouldReceive("changeMessageVisibility")
		->withAnyArgs()
		->andThrows(new Exception("Failure"));

		$message = new Message("queue_url", "Message1", [], [], "afd1cbe8-6ee3-4de0-90f5-50c019a9a887");

		$sqs = new Sqs($mock);

		$this->expectException(ConsumerException::class);
		$sqs->nack($message);
	}
}