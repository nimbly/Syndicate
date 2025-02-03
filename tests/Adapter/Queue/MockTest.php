<?php

namespace Nimbly\Syndicate\Tests\Adapter\Queue;

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\Queue\Mock;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;

/**
 * @covers Nimbly\Syndicate\Adapter\Queue\Mock
 */
class MockTest extends TestCase
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

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = new Mock;

		$this->expectException(PublishException::class);
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

	public function test_consume_failure_throws_consume_exception(): void
	{
		$mock = new Mock;

		$this->expectException(ConsumeException::class);
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

	public function test_flush_all_messages(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("fruits", "ok"));
		$mock->publish(new Message("fruits", "ok"));

		$this->assertCount(2, $mock->getMessages("fruits"));

		$mock->flushMessages();

		$this->assertCount(0, $mock->getMessages("fruits"));
	}

	public function test_flush_topic_messages(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("fruits", "ok"));
		$mock->publish(new Message("fruits", "ok"));
		$mock->publish(new Message("veggies", "ok"));
		$mock->publish(new Message("veggies", "ok"));

		$this->assertCount(2, $mock->getMessages("fruits"));
		$this->assertCount(2, $mock->getMessages("veggies"));

		$mock->flushMessages("fruits");

		$this->assertCount(0, $mock->getMessages("fruits"));
		$this->assertCount(2, $mock->getMessages("veggies"));
	}
}