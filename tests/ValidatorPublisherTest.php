<?php

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\PubSub\Mock;
use Nimbly\Syndicate\ValidatorPublisher;
use Nimbly\Syndicate\MessageValidationException;
use Nimbly\Syndicate\Validators\JsonSchemaValidator;

/**
 * @covers Nimbly\Syndicate\ValidatorPublisher
 */
class ValidatorPublisherTest extends TestCase
{
	public function test_missing_schema_throws_message_validation_exception(): void
	{
		$validator = new JsonSchemaValidator([
			"fruits" => [
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
			]
		]);

		$publisher = new ValidatorPublisher($validator, new Mock);

		$this->expectException(MessageValidationException::class);
		$publisher->publish(new Message("vegetables", "Ok"));
	}

	public function test_message_fails_validation_throws_message_validation_exception(): void
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

		$publisher = new ValidatorPublisher($validator, new Mock);

		$this->expectException(MessageValidationException::class);
		$publisher->publish(new Message("fruits", \json_encode(["name" => "peaches", "published_at" => date("c")])));
	}

	public function test_message_published_on_successful_validation(): void
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

		$mockPublisher = new Mock;
		$publisher = new ValidatorPublisher($validator, $mockPublisher);
		$message = new Message("fruits", \json_encode(["name" => "apples", "published_at" => date("c")]));

		$publisher->publish($message);

		$this->assertCount(1, $mockPublisher->getMessages("fruits"));
		$this->assertSame($message, $mockPublisher->getMessages("fruits")[0]);
	}
}