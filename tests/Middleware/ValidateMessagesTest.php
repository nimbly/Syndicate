<?php

use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\MessageValidationException;
use Nimbly\Syndicate\Middleware\ValidateMessages;
use Nimbly\Syndicate\Response;
use Nimbly\Syndicate\Validators\JsonSchemaValidator;

/**
 * @covers Nimbly\Syndicate\Middleware\ValidateMessages
 */
class ValidateMessagesTest extends TestCase
{
	public function test_handle_missing_schema(): void
	{
		$middleware = new ValidateMessages(
			new JsonSchemaValidator([
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
			])
		);

		$response = $middleware->handle(
			new Message("apples", "Ok"),
			function(Message $message): Response {
				return Response::ack;
			}
		);

		$this->assertEquals(
			Response::deadletter,
			$response
		);
	}

	public function test_handle_invalid_message(): void
	{
		$middleware = new ValidateMessages(
			new JsonSchemaValidator([
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
			])
		);

		$response = $middleware->handle(
			new Message("fruits", \json_encode(["name" => "kiwis", "published_at" => date("c")])),
			function(Message $message): Response {
				return Response::ack;
			}
		);

		$this->assertEquals(
			Response::deadletter,
			$response
		);
	}

	public function test_handle_calls_next(): void
	{
		$middleware = new ValidateMessages(
			new JsonSchemaValidator([
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
			])
		);

		$response = $middleware->handle(
			new Message("fruits", \json_encode(["name" => "apples", "published_at" => date("c")])),
			function(Message $message): Response {
				return Response::ack;
			}
		);

		$this->assertEquals(
			Response::ack,
			$response
		);
	}
}
