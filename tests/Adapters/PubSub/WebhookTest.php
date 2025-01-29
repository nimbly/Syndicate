<?php

namespace Nimbly\Syndicate\Tests\Adapters\PubSub;

use Mockery;
use Exception;
use ReflectionClass;
use Nimbly\Capsule\Request;
use Nimbly\Shuttle\Shuttle;
use Nimbly\Capsule\Response;
use Nimbly\Syndicate\Message;
use function PHPSTORM_META\map;
use PHPUnit\Framework\TestCase;

use Nimbly\Syndicate\Adapter\PubSub\Webhook;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\ConnectionException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @covers Nimbly\Syndicate\Adapter\PubSub\Webhook
 */
class WebhookTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish(): void
	{
		$mockClient = Mockery::mock(Shuttle::class);
		$mockClient->shouldReceive("sendRequest")
			->andReturns(
				new Response(202)
			);

		$publisher = new Webhook(
			$mockClient,
			"https://service.com/events",
			[
				"Content-Type" => "application/json",
				"Authorization" => "Bearer EezohmaiZae2heich7iuthis"
			]
		);

		$publisher->publish(
			new Message(
				topic: "test",
				payload: "Ok",
				headers: ["X-Custom-Header" => "Foo"]
			)
		);

		$mockClient->shouldHaveReceived("sendRequest");
	}

	public function test_publish_with_connection_issue_throws_publisher_exception(): void
	{
		$mockClient = Mockery::mock(Shuttle::class);
		$mockClient->shouldReceive("sendRequest")
			->andReturns(
				new Response(400)
			);

		$publisher = new Webhook(
			$mockClient,
			"https://service.com/events"
		);

		$this->expectException(PublisherException::class);
		$publisher->publish(new Message("test", "Ok"));
	}

	public function test_publish_with_non_2xx_response_throws_publisher_exception(): void
	{
		$mockClient = Mockery::mock(Shuttle::class);
		$mockClient->shouldReceive("sendRequest")
			->andThrows(new Exception("Failure"));

		$publisher = new Webhook(
			$mockClient,
			"https://service.com/events"
		);

		$this->expectException(ConnectionException::class);
		$publisher->publish(new Message("test", "Ok"));
	}

	public function test_build_request_with_defaults(): void
	{
		$publisher = new Webhook(
			new Shuttle,
			"https://service.com/events",
			[
				"Content-Type" => "application/json",
				"Authorization" => "Bearer EezohmaiZae2heich7iuthis"
			]
		);

		$reflectionClass = new ReflectionClass($publisher);
		$reflectionMethod = $reflectionClass->getMethod("buildRequest");

		$message = new Message(
			topic: "test",
			payload: "Ok",
			headers: ["Message-Header" => "Syndicate"]
		);

		/**
		 * @var Request $request
		 */
		$request = $reflectionMethod->invoke($publisher, $message);

		$this->assertInstanceOf(Request::class, $request);

		$this->assertEquals(
			"POST",
			$request->getMethod()
		);

		$this->assertEquals(
			"https://service.com/events/test",
			(string) $request->getUri()
		);

		$this->assertEquals(
			"application/json",
			$request->getHeaderLine("Content-Type")
		);

		$this->assertEquals(
			"Bearer EezohmaiZae2heich7iuthis",
			$request->getHeaderLine("Authorization")
		);

		$this->assertEquals(
			"Syndicate",
			$request->getHeaderLine("Message-Header")
		);

		$this->assertEquals(
			"Ok",
			$request->getBody()->getContents()
		);
	}

	public function test_build_request_with_overrides(): void
	{
		$publisher = new Webhook(
			new Shuttle,
			"https://service.com/events",
			[
				"Content-Type" => "application/json",
				"Authorization" => "Bearer EezohmaiZae2heich7iuthis"
			]
		);

		$reflectionClass = new ReflectionClass($publisher);
		$reflectionMethod = $reflectionClass->getMethod("buildRequest");

		$message = new Message(
			topic: "test",
			payload: "Ok",
			headers: ["Message-Header" => "Syndicate"]
		);

		$options = [
			"method" => "PUT",
			"uri" => "https://test.com/",
			"headers" => [
				"Override-Header-1" => "Value1",
				"Override-Header-2" => "Value2"
			]
		];

		/**
		 * @var Request $request
		 */
		$request = $reflectionMethod->invoke($publisher, $message, $options);

		$this->assertInstanceOf(Request::class, $request);

		$this->assertEquals(
			"PUT",
			$request->getMethod()
		);

		$this->assertEquals(
			"https://test.com/",
			(string) $request->getUri()
		);

		$this->assertEquals(
			"application/json",
			$request->getHeaderLine("Content-Type")
		);

		$this->assertEquals(
			"Bearer EezohmaiZae2heich7iuthis",
			$request->getHeaderLine("Authorization")
		);

		$this->assertEquals(
			"Syndicate",
			$request->getHeaderLine("Message-Header")
		);

		$this->assertEquals(
			"Value1",
			$request->getHeaderLine("Override-Header-1")
		);

		$this->assertEquals(
			"Value2",
			$request->getHeaderLine("Override-Header-2")
		);

		$this->assertEquals(
			"Ok",
			$request->getBody()->getContents()
		);
	}
}