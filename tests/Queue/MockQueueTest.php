<?php

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Queue\Mock;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;

/**
 * @covers Nimbly\Syndicate\Queue\Mock
 */
class MockQueueTest extends TestCase
{
	public function test_publish(): void
	{
		$message = new Message("test", "Ok");

		$mock = new Mock;
		$mock->publish($message);

		$messages = $mock->getMessages("test");

		$this->assertCount(1, $messages);
		$this->assertSame($message, $messages[0]);
	}

	public function test_publish_failure_throws_publisher_exception(): void
	{
		$mock = new Mock;

		$this->expectException(PublisherException::class);
		$mock->publish(new Message("test", "Ok"), ["exception" => true]);
	}

	public function test_consume(): void
	{
		$mock = new Mock([
			"test" => [
				new Message("test", "message1"),
				new Message("test", "message2"),
			]
		]);

		$messages = $mock->consume("test", 10);

		$this->assertCount(2, $messages);
	}

	public function test_consume_unknown_topic_returns_empty_array(): void
	{
		$mock = new Mock;
		$messages = $mock->consume("test");
		$this->assertCount(0, $messages);
	}

	public function test_consume_failure_throws_consumer_exception(): void
	{
		$mock = new Mock;

		$this->expectException(ConsumerException::class);
		$mock->consume("test", 10, ["exception" => true]);
	}

	public function test_ack(): void
	{
		$message = new Message("test", "message1");

		$mock = new Mock;
		$response = $mock->ack($message);

		$this->assertNull($response);
	}

	public function test_nack(): void
	{
		$message = new Message("test", "message1");

		$mock = new Mock;
		$mock->nack($message);

		$this->assertCount(1, $mock->getMessages("test"));
	}
}