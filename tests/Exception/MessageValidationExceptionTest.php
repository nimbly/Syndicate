<?php

namespace Nimbly\Syndicate\Tests\Exception;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\MessageValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageValidationException::class)]
class MessageValidationExceptionTest extends TestCase
{
	public function test_get_failed_message(): void
	{
		$message = new Message("test", "Ok");

		$exception = new MessageValidationException("Fail", $message);

		$this->assertSame(
			$message,
			$exception->getFailedMessage()
		);
	}

	public function test_get_context(): void
	{
		$message = new Message("test", "Ok");

		$exception = new MessageValidationException("Fail", $message, ["message" => "Schema error", "data" => "Foo", "path" => "$.path"]);

		$this->assertEquals(
			["message" => "Schema error", "data" => "Foo", "path" => "$.path"],
			$exception->getContext()
		);
	}
}