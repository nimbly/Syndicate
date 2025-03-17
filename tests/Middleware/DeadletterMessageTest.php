<?php

namespace Nimbly\Syndicate\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use Nimbly\Syndicate\Adapter\MockQueue;
use Nimbly\Syndicate\Filter\RedirectFilter;
use Nimbly\Syndicate\Middleware\DeadletterMessage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DeadletterMessage::class)]
class DeadletterMessageTest extends TestCase
{
	public function test_deadletter_response(): void
	{
		$mock = new MockQueue;
		$publisher = new RedirectFilter($mock, "deadletter");

		$middleware = new DeadletterMessage($publisher);
		$response = $middleware->handle(
			new Message("test", "Ok"),
			function(): Response {
				return Response::deadletter;
			}
		);

		$this->assertCount(1, $mock->getMessages("deadletter"));
		$this->assertEquals(Response::ack, $response);
	}

	public function test_other_response(): void
	{
		$mock = new MockQueue;
		$publisher = new RedirectFilter($mock, "deadletter");

		$middleware = new DeadletterMessage($publisher);
		$response = $middleware->handle(
			new Message("test", "Ok"),
			function(): Response {
				return Response::ack;
			}
		);

		$this->assertCount(0, $mock->getMessages("deadletter"));
		$this->assertEquals(Response::ack, $response);
	}
}