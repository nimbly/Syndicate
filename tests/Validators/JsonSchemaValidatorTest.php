<?php

namespace Nimbly\Syndicate\Tests\Validators;

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Validator\MessageValidationException;
use Nimbly\Syndicate\Validator\JsonSchemaValidator;

/**
 * @covers Nimbly\Syndicate\Validator\JsonSchemaValidator
 */
class JsonSchemaValidatorTest extends TestCase
{
	public function test_missing_schema_throws_message_validation_exception_by_default(): void
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

	public function test_missing_schema_returns_true_if_ignore_missing(): void
	{
		$validator = new JsonSchemaValidator(
			schemas: [],
			ignore_missing_schemas: true
		);

		$result = $validator->validate(new Message("vegetables", "Ok"));
		$this->assertTrue($result);
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

	public function test_failed_validation_includes_context(): void
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

		try {

			$validator->validate(new Message("fruits", \json_encode(["name" => "kiwis", "published_at" => date("c")])));
		}
		catch( MessageValidationException $exception )
		{}

		$this->assertNotEmpty(
			$exception->getContext()["message"]
		);

		$this->assertEquals(
			"kiwis",
			$exception->getContext()["data"]
		);

		$this->assertEquals(
			"$.name",
			$exception->getContext()["path"]
		);
	}

	public function test_failed_validation_message_includes_multiple_args(): void
	{
		$validator = new JsonSchemaValidator([
			"fruits" => \json_encode([
				"type" => "object",
				"properties" => [
					"name" => [
						"type" => "string",
						"maxLength" => 4
					],

					"published_at" => [
						"type" => "string",
						"format" => "date-time"
					]
				],
				"required" => ["name", "published_at"],
			])
		]);

		try {

			$validator->validate(new Message("fruits", \json_encode(["name" => "pineapples", "published_at" => date("c")])));
		}
		catch( MessageValidationException $exception )
		{}

		$this->assertEquals(
			"Maximum string length is 4, found 10",
			$exception->getContext()["message"]
		);
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