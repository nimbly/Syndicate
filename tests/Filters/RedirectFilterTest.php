<?php

namespace Nimbly\Syndicate\Tests\Filters;

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\Queue\Mock;
use Nimbly\Syndicate\Filter\RedirectFilter;

/**
 * @covers Nimbly\Syndicate\Filter\RedirectFilter
 */
class RedirectFilterTest extends TestCase
{
	public function test_publish_returns_receipt(): void
	{
		$mock = new Mock;

		$deadletter = new RedirectFilter($mock, "deadletter");

		$receipt = $deadletter->publish(
			new Message("test", "payload", ["attr1" => "val1"], ["hdr1" => "val1"])
		);

		$this->assertNotNull($receipt);
	}

	public function test_publish_copies_original_messag(): void
	{
		$mock = new Mock;

		$deadletter = new RedirectFilter($mock, "deadletter");
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