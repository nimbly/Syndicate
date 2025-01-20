<?php

use Nimbly\Syndicate\JsonSchemaPublisher;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\MessageValidationException;
use Nimbly\Syndicate\PubSub\Mock;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\JsonSchemaPublisher
 */
class JsonSchemaPublisherTest extends TestCase
{
	public function test_missing_schema_throws_message_validation_exception(): void
	{
		$publisher = new JsonSchemaPublisher(
			new Mock,
			[
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
			]
		);

		$this->expectException(MessageValidationException::class);
		$publisher->publish(new Message("vegetables", "Ok"));
	}

	public function test_failed_validation_throws_message_validation_exception(): void
	{
		$publisher = new JsonSchemaPublisher(
			new Mock,
			[
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
			]
		);

		$this->expectException(MessageValidationException::class);
		$publisher->publish(new Message("fruits", \json_encode(["name" => "kiwis", "published_at" => date("c")])));
	}

	public function test_message_published(): void
	{
		$mockPublisher = new Mock;

		$publisher = new JsonSchemaPublisher(
			$mockPublisher,
			[
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
			]
		);

		$message = new Message(
			"fruits",
			\json_encode(["name" => "apples", "published_at" => date("c")])
		);

		$publisher->publish($message);

		$this->assertCount(1, $mockPublisher->getMessages("fruits"));
		$this->assertSame($message, $mockPublisher->getMessages("fruits")[0]);
	}
}