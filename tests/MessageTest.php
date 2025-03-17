<?php

namespace Nimbly\Syndicate\Tests;

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
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

	public function test_set_parsed_payload(): void
	{
		$payload = ["id" => "6f7383c4-e34b-4ba6-b1da-b5600f492098", "name" => "John Doe"];
		$message = new Message("test", \json_encode($payload));
		$message->setParsedPayload($payload);

		$this->assertEquals($payload, $message->getParsedPayload());
	}
}