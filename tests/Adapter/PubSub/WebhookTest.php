<?php

namespace Nimbly\Syndicate\Tests\Adapter\PubSub;

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
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nimbly\Capsule\HttpMethod;

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

	public function test_publish_with_connection_issue_throws_publish_exception(): void
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

		$this->expectException(PublishException::class);
		$publisher->publish(new Message("test", "Ok"));
	}

	public function test_publish_with_non_2xx_response_throws_publish_exception(): void
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
			"https://service.com/events/",
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
			topic: "https://events.example.com/test",
			payload: "Ok",
			headers: ["Message-Header" => "Syndicate", "Authorization" => "Bearer abc123"]
		);

		/**
		 * @var Request $request
		 */
		$request = $reflectionMethod->invoke($publisher, $message, ["method" => HttpMethod::PUT]);

		$this->assertEquals(
			"PUT",
			$request->getMethod()
		);

		$this->assertEquals(
			"https://events.example.com/test",
			(string) $request->getUri()
		);

		$this->assertEquals(
			"application/json",
			$request->getHeaderLine("Content-Type")
		);

		$this->assertEquals(
			"Bearer abc123",
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
}