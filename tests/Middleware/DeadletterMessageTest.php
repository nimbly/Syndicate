<?php

namespace Nimbly\Syndicate\Tests\Middleware;

use Nimbly\Syndicate\DeadletterPublisher;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Queue\Mock;
use Nimbly\Syndicate\Middleware\DeadletterMessage;

/**
 * @covers Nimbly\Syndicate\Middleware\DeadletterMessage
 */
class DeadletterMessageTest extends TestCase
{
	public function test_deadletter_response(): void
	{
		$mock = new Mock;
		$publisher = new DeadletterPublisher($mock, "deadletter");

		$middleware = new DeadletterMessage($publisher);
		$response = $middleware->handle(
			new Message("test", "Ok"),
			function(): Response {
				return Response::deadletter;
			}
		);

		$this->assertCount(1, $mock->getMessages("deadletter"));
		$this->assertEquals(
			Response::ack,
			$response
		);
	}

	public function test_other_response(): void
	{
		$mock = new Mock;
		$publisher = new DeadletterPublisher($mock, "deadletter");

		$middleware = new DeadletterMessage($publisher);
		$response = $middleware->handle(
			new Message("test", "Ok"),
			function(): Response {
				return Response::ack;
			}
		);

		$this->assertCount(0, $mock->getMessages("deadletter"));
		$this->assertEquals(
			Response::ack,
			$response
		);
	}
}