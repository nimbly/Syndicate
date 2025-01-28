<?php

namespace Nimbly\Syndicate\Tests;

use Nimbly\Syndicate\Message;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Validator\JsonSchemaValidationException;

/**
 * @covers Nimbly\Syndicate\Validator\JsonSchemaValidationException
 */
class JsonSchemaValidationExceptionTest extends TestCase
{
	public function test_get_failed_message(): void
	{
		$message = new Message("test", "Ok");

		$exception = new JsonSchemaValidationException("Fail", $message);

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

		$exception = new JsonSchemaValidationException("Fail", $message, $result->error());

		$this->assertSame(
			$result->error(),
			$exception->getValidationError()
		);
	}
}