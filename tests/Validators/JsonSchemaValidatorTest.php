<?php

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\MessageValidationException;
use Nimbly\Syndicate\Validators\JsonSchemaValidator;

/**
 * @covers Nimbly\Syndicate\Validators\JsonSchemaValidator
 */
class JsonSchemaValidatorTest extends TestCase
{
	public function test_missing_schema_throws_message_validation_exception(): void
	{
		$validator = new JsonSchemaValidator([
			"fruits" => \json_encode([
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
			])
		]);

		$this->expectException(MessageValidationException::class);
		$validator->validate(new Message("vegetables", "Ok"));
	}

	public function test_failed_validation_throws_message_validation_exception(): void
	{
		$validator = new JsonSchemaValidator([
			"fruits" => \json_encode([
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
			])
		]);

		$this->expectException(MessageValidationException::class);
		$validator->validate(new Message("fruits", \json_encode(["name" => "kiwis", "published_at" => date("c")])));
	}

	public function test_message_passes(): void
	{
		$validator = new JsonSchemaValidator([
			"fruits" => \json_encode([
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
			])
		]);

		$message = new Message(
			"fruits",
			\json_encode(["name" => "apples", "published_at" => date("c")])
		);

		$valid = $validator->validate($message);

		$this->assertTrue($valid);
	}
}