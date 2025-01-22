<?php

namespace Nimbly\Syndicate\Tests;

use Nimbly\Syndicate\Consume;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\Consume
 */
class ConsumeAttributeTest extends TestCase
{
	public function test_get_topic(): void
	{
		$consume = new Consume(topic: "topic");

		$this->assertEquals(
			"topic",
			$consume->getTopic()
		);
	}

	public function test_get_payload(): void
	{
		$consume = new Consume(payload: ["name" => "value"]);

		$this->assertEquals(
			["name" => "value"],
			$consume->getPayload()
		);
	}

	public function test_get_headers(): void
	{
		$consume = new Consume(headers: ["name" => "value"]);

		$this->assertEquals(
			["name" => "value"],
			$consume->getHeaders()
		);
	}

	public function test_get_attributes(): void
	{
		$consume = new Consume(attributes: ["name" => "value"]);

		$this->assertEquals(
			["name" => "value"],
			$consume->getAttributes()
		);
	}
}