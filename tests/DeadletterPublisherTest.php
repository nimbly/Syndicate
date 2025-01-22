<?php

namespace Nimbly\Syndicate\Tests;

use Nimbly\Syndicate\DeadletterPublisher;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Queue\Mock;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\DeadletterPublisher
 */
class DeadletterPublisherTest extends TestCase
{
	public function test_publish_returns_receipt(): void
	{
		$mock = new Mock;

		$deadletter = new DeadletterPublisher(
			$mock,
			"deadletter"
		);

		$receipt = $deadletter->publish(
			new Message("test", "payload", ["attr1" => "val1"], ["hdr1" => "val1"])
		);

		$this->assertNotNull($receipt);
	}

	public function test_publish_copies_original_messag(): void
	{
		$mock = new Mock;

		$deadletter = new DeadletterPublisher(
			$mock,
			"deadletter"
		);

		$deadletter->publish(
			new Message("test", "payload", ["attr1" => "val1"], ["hdr1" => "val1"])
		);

		$messages = $mock->consume("deadletter", 10);

		$this->assertCount(1, $messages);

		$this->assertEquals(
			"payload",
			$messages[0]->getPayload()
		);

		$this->assertEquals(
			["attr1" => "val1"],
			$messages[0]->getAttributes()
		);

		$this->assertEquals(
			["hdr1" => "val1"],
			$messages[0]->getHeaders()
		);
	}
}