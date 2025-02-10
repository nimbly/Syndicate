<?php

namespace Nimbly\Syndicate\Tests\Filter;

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\MockQueue;
use Nimbly\Syndicate\Filter\RedirectFilter;

/**
 * @covers Nimbly\Syndicate\Filter\RedirectFilter
 */
class RedirectFilterTest extends TestCase
{
	public function test_publish_returns_receipt(): void
	{
		$mock = new MockQueue;

		$filter = new RedirectFilter($mock, "deadletter");

		$receipt = $filter->publish(
			new Message("test", "payload", ["attr1" => "val1"], ["hdr1" => "val1"])
		);

		$this->assertNotNull($receipt);
	}

	public function test_publish_redirects_to_given_topic(): void
	{
		$mock = new MockQueue;

		$filter = new RedirectFilter($mock, "deadletter");
		$filter->publish(
			new Message("test", "payload", ["attr1" => "val1"], ["hdr1" => "val1"])
		);

		$messages = $mock->getMessages("deadletter");

		$this->assertCount(1, $messages);
	}

	public function test_publish_copies_original_message(): void
	{
		$mock = new MockQueue;

		$filter = new RedirectFilter($mock, "deadletter");
		$filter->publish(
			new Message("test", "payload", ["attr1" => "val1"], ["hdr1" => "val1"])
		);

		$message = $mock->getMessages("deadletter")[0];

		$this->assertEquals(
			"payload",
			$message->getPayload()
		);

		$this->assertEquals(
			["attr1" => "val1"],
			$message->getAttributes()
		);

		$this->assertEquals(
			["hdr1" => "val1"],
			$message->getHeaders()
		);
	}
}