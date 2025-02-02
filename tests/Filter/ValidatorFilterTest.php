<?php

namespace Nimbly\Syndicate\Tests\Filter;

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\Queue\Mock;
use Nimbly\Syndicate\Filter\ValidatorFilter;
use Nimbly\Syndicate\Validator\JsonSchemaValidator;
use Nimbly\Syndicate\Exception\MessageValidationException;
use Nimbly\Syndicate\Validator\ValidatorInterface;

/**
 * @covers Nimbly\Syndicate\Filter\ValidatorFilter
 */
class ValidatorFilterTest extends TestCase
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

		$publisher = new ValidatorFilter($validator, new Mock);

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

		$publisher = new ValidatorFilter($validator, new Mock);

		$this->expectException(MessageValidationException::class);
		$publisher->publish(new Message("fruits", \json_encode(["name" => "peaches", "published_at" => date("c")])));
	}

	public function test_validator_returns_false_throws_message_validation_exception(): void
	{
		$publisher = new ValidatorFilter(
			new class implements ValidatorInterface {
				public function validate(Message $message): bool {
					return false;
				}
			},
			new Mock
		);

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
		$publisher = new ValidatorFilter($validator, $mockPublisher);
		$message = new Message("fruits", \json_encode(["name" => "apples", "published_at" => date("c")]));

		$publisher->publish($message);

		$this->assertCount(1, $mockPublisher->getMessages("fruits"));
		$this->assertSame($message, $mockPublisher->getMessages("fruits")[0]);
	}
}