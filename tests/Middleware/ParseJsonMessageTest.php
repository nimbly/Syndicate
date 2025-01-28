<?php

namespace Nimbly\Syndicate\Tests\Middleware;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Middleware\ParseJsonMessage;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @covers Nimbly\Syndicate\Middleware\ParseJsonMessage
 */
class ParseJsonMessageTest extends TestCase
{
	public function test_invalid_json_throws_unexpected_value_exception(): void
	{
		$middleware = new ParseJsonMessage;

		$this->expectException(UnexpectedValueException::class);
		$middleware->handle(
			new Message("test", "not-json"),
			fn() => null,
		);
	}

	public function test_message_contains_parsed_payload(): void
	{
		$middleware = new ParseJsonMessage;

		$payload = ["status" => "ok", "published_at" => "2025-01-25T17:25:23Z"];

		$message = $middleware->handle(
			new Message("test", \json_encode($payload)),
			fn(Message $message) => $message,
		);

		$this->assertEquals(
			(object) $payload,
			$message->getParsedPayload()
		);
	}

	public function test_associative_array_parsing(): void
	{
		$middleware = new ParseJsonMessage(associative: true);

		$payload = ["status" => "ok", "published_at" => "2025-01-25T17:25:23Z"];

		$message = $middleware->handle(
			new Message("test", \json_encode($payload)),
			fn(Message $message) => $message,
		);

		$this->assertEquals(
			$payload,
			$message->getParsedPayload()
		);
	}
}