<?php

namespace Nimbly\Syndicate\Tests;

use ReflectionClass;
use Nimbly\Syndicate\Router;
use Nimbly\Syndicate\Consume;
use Nimbly\Syndicate\Message;
use UnexpectedValueException;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\RoutingException;
use Nimbly\Syndicate\Tests\Fixtures\TestHandler;

/**
 * @covers Nimbly\Syndicate\Router
 */
class RouterTest extends TestCase
{
	public function test_constructor(): void
	{
		$router = new Router([TestHandler::class]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionProperty = $reflectionClass->getProperty("routes");
		$reflectionProperty->setAccessible(true);

		$routes = $reflectionProperty->getValue($router);

		$this->assertEquals(
			"\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onUserCreated",
			\array_keys($routes)[0]
		);

		$this->assertEquals(
			"users",
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onUserCreated"]->getTopic()
		);

		$this->assertEquals(
			["$.event" => "UserCreated"],
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onUserCreated"]->getPayload()
		);

		$this->assertEquals(
			["role" => "user"],
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onUserCreated"]->getAttributes()
		);

		$this->assertEquals(
			["origin" => "value"],
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onUserCreated"]->getHeaders()
		);

		$this->assertEquals(
			"\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onAdminDeleted",
			\array_keys($routes)[1]
		);

		$this->assertEquals(
			"admins",
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onAdminDeleted"]->getTopic()
		);

		$this->assertEquals(
			["$.event" => "AdminDeleted"],
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onAdminDeleted"]->getPayload()
		);

		$this->assertEquals(
			["role" => "admin"],
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onAdminDeleted"]->getAttributes()
		);

		$this->assertEquals(
			["origin" => "value"],
			$routes["\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onAdminDeleted"]->getHeaders()
		);
	}

	public function test_method_without_consume_attribute_is_skipped(): void
	{
		$router = new Router([
			new class {
				protected function helper(): void
				{
				}

				#[Consume(topic: "test")]
				public function onFoo(Message $message)
				{
				}
			}
		]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionProperty = $reflectionClass->getProperty("routes");
		$reflectionProperty->setAccessible(true);

		$routes = $reflectionProperty->getValue($router);

		$this->assertCount(1, $routes);
	}

	public function test_method_with_consume_attribute_and_non_public_visibility_throws_routing_exception(): void
	{
		$this->expectException(RoutingException::class);
		$router = new Router([
			new class {

				#[Consume(topic: "test")]
				private function onFoo(Message $message) {

				}
			}
		]);
	}

	public function test_method_with_multiple_consume_attributes_throws_routing_exception(): void
	{
		$this->expectException(RoutingException::class);
		$router = new Router([
			new class {

				#[Consume(topic: "test")]
				#[Consume(topic: "foo")]
				public function onFoo(Message $message) {

				}
			}
		]);
	}

	public function test_build_regex(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("buildRegex");
		$reflectionMethod->setAccessible(true);

		$regex = $reflectionMethod->invoke($router, "Syndicate/*/Messages");

		$this->assertEquals(
			"Syndicate\/.*\/Messages",
			$regex
		);

		$regex = $reflectionMethod->invoke($router, "Messages.Users/*");

		$this->assertEquals(
			"Messages\.Users\/.*",
			$regex
		);
	}

	public function test_match_string(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchString");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke($router, "Messages/Users/Create", "Messages/*/Create");

		$this->assertTrue($match);
	}

	public function test_match_string_no_patterns_returns_true(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchString");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke($router, "Messages/Users/Create", "");
		$this->assertTrue($match);

		$match = $reflectionMethod->invoke($router, "Messages/Users/Create", []);
		$this->assertTrue($match);
	}

	public function test_match_string_false(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchString");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke($router, "Messages/Users/Update", "Messages/*/Create");

		$this->assertFalse($match);
	}

	public function test_match_string_multiple(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchString");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke($router, "Messages/Users/Update", ["Messages/*/Create", "Messages/*/Update"]);

		$this->assertTrue($match);
	}

	public function test_match_string_multiple_mismatches(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchString");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke($router, "Messages/Users/Delete", ["Messages/*/Create", "Messages/*/Update"]);

		$this->assertFalse($match);
	}

	public function test_match_key_value_pairs(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchKeyValuePairs");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke(
			$router,
			["Header1" => "Value1", "Header2" => "Value2"],
			["Header1" => "Value*", "Header2" => "Value*"]
		);

		$this->assertTrue($match);
	}

	public function test_match_key_value_pairs_no_patterns_returns_true(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchKeyValuePairs");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke(
			$router,
			["Header1" => "Value1", "Header2" => "Value2"],
			[]
		);

		$this->assertTrue($match);
	}

	public function test_match_key_value_pairs_missing_key_returns_false(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchKeyValuePairs");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke(
			$router,
			["Header1" => "Value1"],
			["Header2" => "Value2"]
		);

		$this->assertFalse($match);
	}

	public function test_match_key_value_pairs_false(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchKeyValuePairs");
		$reflectionMethod->setAccessible(true);

		$match = $reflectionMethod->invoke(
			$router,
			["Header1" => "Value1", "Header2" => "Value2"],
			["Header1" => "User/*", "Header2" => "Account/*"]
		);

		$this->assertFalse($match);
	}

	public function test_match_json(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchJson");
		$reflectionMethod->setAccessible(true);

		$json = [
			"id" => "421d7fdb-f552-4214-bda3-62e4fd64d7ef",
			"topic" => "users",
			"origin" => "Syndicate",
			"event" => "UserCreated",
			"body" => [
				"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
				"name" => "John Doe",
				"email" => "john@example.com",
				"role" => "admin"
			]
		];

		$match = $reflectionMethod->invoke(
			$router,
			\json_encode($json),
			["$.event" => "User*", "$.body.role" => ["admin", "user"]]
		);

		$this->assertTrue($match);
	}

	public function test_match_json_empty_patterns_returns_true(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchJson");
		$reflectionMethod->setAccessible(true);

		$json = [
			"id" => "421d7fdb-f552-4214-bda3-62e4fd64d7ef",
			"topic" => "users",
			"origin" => "Syndicate",
			"event" => "UserCreated",
			"body" => [
				"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
				"name" => "John Doe",
				"email" => "john@example.com",
				"role" => "admin"
			]
		];

		$match = $reflectionMethod->invoke(
			$router,
			\json_encode($json),
			[]
		);

		$this->assertTrue($match);
	}

	public function test_match_json_failed_decoding_throws_unexpected_value_exception(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchJson");
		$reflectionMethod->setAccessible(true);

		$json = "id: 421d7fdb-f552-4214-bda3-62e4fd64d7ef";

		$this->expectException(UnexpectedValueException::class);
		$reflectionMethod->invoke($router,	$json, ["$.body.title" => "books"]);
	}

	public function test_match_json_path_empty_data_returns_false(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchJson");
		$reflectionMethod->setAccessible(true);

		$json = [
			"id" => "421d7fdb-f552-4214-bda3-62e4fd64d7ef",
			"topic" => "users",
			"origin" => "Syndicate",
			"event" => "UserCreated",
			"body" => [
				"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
				"name" => "John Doe",
				"email" => "john@example.com",
				"role" => "admin"
			]
		];

		$match = $reflectionMethod->invoke(
			$router,
			\json_encode($json),
			["$.body.title" => "books"]
		);

		$this->assertFalse($match);
	}

	public function test_match_json_matched_multiple_values_throws_exception(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchJson");
		$reflectionMethod->setAccessible(true);

		$json = [
			"id" => "421d7fdb-f552-4214-bda3-62e4fd64d7ef",
			"topic" => "users",
			"origin" => "Syndicate",
			"event" => "UserCreated",
			"body" => [
				"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
				"name" => "John Doe",
				"email" => "john@example.com",
				"role" => "admin"
			]
		];

		$this->expectException(UnexpectedValueException::class);
		$reflectionMethod->invoke(
			$router,
			\json_encode($json),
			["$.event" => "UserCreated", "$.body" => "user"]
		);
	}

	public function test_match_json_no_match_returns_false(): void
	{
		$router = new Router([]);

		$reflectionClass = new ReflectionClass($router);
		$reflectionMethod = $reflectionClass->getMethod("matchJson");
		$reflectionMethod->setAccessible(true);

		$json = [
			"id" => "421d7fdb-f552-4214-bda3-62e4fd64d7ef",
			"topic" => "users",
			"origin" => "Syndicate",
			"event" => "UserCreated",
			"body" => [
				"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
				"name" => "John Doe",
				"email" => "john@example.com",
				"role" => "admin"
			]
		];

		$match = $reflectionMethod->invoke(
			$router,
			\json_encode($json),
			["$.event" => "UserCreated", "$.body.role" => "user"]
		);

		$this->assertFalse($match);
	}

	public function test_resolve(): void
	{
		$router = new Router([TestHandler::class]);

		$handler = $router->resolve(
			new Message(
				topic: "users",
				payload: \json_encode([
					"event" => "UserCreated"
				]),
				attributes: [
					"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
					"role" => "user"
				],
				headers: [
					"origin" => "value",
					"published_at" => "2024-11-23T14:46:03Z"
				]
			)
		);

		$this->assertEquals(
			"\\Nimbly\\Syndicate\\Tests\\Fixtures\\TestHandler@onUserCreated",
			$handler
		);
	}

	public function test_resolve_no_match_returns_default_handler(): void
	{
		$default = function(): Response {
			return Response::deadletter;
		};

		$router = new Router(
			handlers: [TestHandler::class],
			default: $default
		);

		$handler = $router->resolve(
			new Message(
				topic: "users",
				payload: \json_encode([
					"event" => "UserUpdated"
				]),
				attributes: [
					"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
					"role" => "user"
				],
				headers: [
					"origin" => "value",
					"published_at" => "2024-11-23T14:46:03Z"
				]
			)
		);

		$this->assertSame($default, $handler);
	}

	public function test_resolve_no_match_returns_null_if_no_default_handler(): void
	{
		$router = new Router(
			handlers: [TestHandler::class]
		);

		$handler = $router->resolve(
			new Message(
				topic: "users",
				payload: \json_encode([
					"event" => "UserUpdated"
				]),
				attributes: [
					"id" => "efa8157c-953f-4ff7-9664-21bf3101ae51",
					"role" => "user"
				],
				headers: [
					"origin" => "value",
					"published_at" => "2024-11-23T14:46:03Z"
				]
			)
		);

		$this->assertNull($handler);
	}
}