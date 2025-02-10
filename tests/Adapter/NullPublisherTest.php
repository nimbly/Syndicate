<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Nimbly\Syndicate\Adapter\NullPublisher;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * @covers Nimbly\Syndicate\Adapter\NullPublisher
 */
class NullPublisherTest extends TestCase
{
	public function test_publish_returns_random_hex_string(): void
	{
		$publisher = new NullPublisher;
		$receipt = $publisher->publish(new Message("fruits", "Ok"));

		$this->assertMatchesRegularExpression(
			"/^[a-f0-9]+$/i",
			$receipt
		);
	}

	public function test_receipt_callback(): void
	{
		$publisher = new NullPublisher(
			fn(Message $message) => $message->getAttributes()["id"]
		);

		$receipt = $publisher->publish(
			new Message("fruits", "Ok", ["id" => "e34b738c-b8b1-46f4-9802-247e7c36a246"])
		);

		$this->assertEquals(
			"e34b738c-b8b1-46f4-9802-247e7c36a246",
			$receipt
		);
	}
}