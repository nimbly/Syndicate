<?php

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\Message
 */
class MessageTest extends TestCase
{
	public function test_get_topic(): void
	{
		$message = new Message("test", "payload");

		$this->assertEquals(
			"test",
			$message->getTopic()
		);
	}

	public function test_get_payload(): void
	{
		$message = new Message("test", "payload");

		$this->assertEquals(
			"payload",
			$message->getPayload()
		);
	}

	public function test_get_attributes(): void
	{
		$message = new Message("test", "payload", ["attr1" => "val1", "attr2" => "val2"]);

		$this->assertEquals(
			["attr1" => "val1", "attr2" => "val2"],
			$message->getAttributes()
		);
	}

	public function test_get_headers(): void
	{
		$message = new Message("test", "payload", [], ["hdr1" => "val1", "hdr2" => "val2"]);

		$this->assertEquals(
			["hdr1" => "val1", "hdr2" => "val2"],
			$message->getHeaders()
		);
	}

	public function test_get_reference(): void
	{
		$message = new Message("test", "payload", [], [], "reference");

		$this->assertEquals(
			"reference",
			$message->getReference()
		);
	}
}