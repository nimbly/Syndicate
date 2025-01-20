<?php

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\MessageValidationException;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\MessageValidationException
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

	public function test_get_validation_error(): void
	{
		$message = new Message("test", "Ok");

		$schema = [
			"type" => "object",
			"properties" => [
				"name" => [
					"type" => "string",
					"enum" => ["apples", "bananas"]
				],

				"published_at" => [
					"type" => "string",
					"format" => "date-time"
				]
			],
			"required" => ["name", "published_at"],
		];

		$data = (object) [
			"name" => "kiwis",
			"published_at" => date("c")
		];

		$validator = new Validator;
		$result = $validator->validate($data, \json_encode($schema));

		$exception = new MessageValidationException("Fail", $message, $result->error());

		$this->assertSame(
			$result->error(),
			$exception->getValidationError()
		);
	}
}