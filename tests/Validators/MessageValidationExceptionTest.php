<?php

namespace Nimbly\Syndicate\Tests;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Validator\MessageValidationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\Validator\MessageValidationException
 */
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
}