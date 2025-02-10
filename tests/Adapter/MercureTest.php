<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Nimbly\Capsule\Request;
use Nimbly\Shuttle\Shuttle;
use Nimbly\Capsule\Response;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Capsule\ResponseStatus;
use Nimbly\Shuttle\RequestException;
use Nimbly\Shuttle\Handler\MockHandler;
use Nimbly\Syndicate\Adapter\Mercure;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;

/**
 * @covers Nimbly\Syndicate\Adapter\Mercure
 */
class MercureTest extends TestCase
{
	public function test_publish(): void
	{
		$publisher = new Mercure(
			hub: "https://example.hub/.well-known/mercure",
			token: "jwt-token",
			httpClient: new Shuttle(
				handler: new MockHandler([
					fn(Request $request) => new Response(
						statusCode: ResponseStatus::OK,
						body: \json_encode([
							"uri" => (string) $request->getUri(),
							"method" => $request->getMethod(),
							"body" => $request->getBody()->getContents(),
							"headers" => $request->getHeaders()
						]))
				])
			)
		);

		$receipt = $publisher->publish(
			new Message("fruits", "bananas", ["id" =>"dc3ed6b1-582e-4e0d-976a-2d8c5657c63d", "private" => true, "type" => "event", "retry" => 30])
		);

		$payload = \json_decode($receipt);

		$this->assertEquals("POST", $payload->method);
		$this->assertEquals("https://example.hub/.well-known/mercure", $payload->uri);
		$this->assertEquals("topic=fruits&data=bananas&id=dc3ed6b1-582e-4e0d-976a-2d8c5657c63d&private=1&type=event", $payload->body);
		$this->assertEquals(
			"application/x-www-form-urlencoded",
			$payload->headers->{"Content-Type"}[0]
		);
		$this->assertEquals(
			"Bearer jwt-token",
			$payload->headers->{"Authorization"}[0]
		);
	}

	public function test_publish_request_exception_throws_connection_exception(): void
	{
		$publisher = new Mercure(
			hub: "https://example.hub/.well-known/mercure",
			token: "jwt-token",
			httpClient: new Shuttle(
				handler: new MockHandler([
					fn(Request $request) => throw new RequestException($request, "Failed to connect")
				])
			)
		);

		$this->expectException(ConnectionException::class);

		$publisher->publish(
			new Message("fruits", "bananas")
		);
	}

	public function test_publish_request_failure_throws_publish_exception(): void
	{
		$publisher = new Mercure(
			hub: "https://example.hub/.well-known/mercure",
			token: "jwt-token",
			httpClient: new Shuttle(
				handler: new MockHandler([
					new Response(ResponseStatus::UNAUTHORIZED)
				])
			)
		);

		$this->expectException(PublishException::class);

		$publisher->publish(
			new Message("fruits", "bananas")
		);
	}

	public function test_publish_returns_receipt(): void
	{
		$publisher = new Mercure(
			hub: "https://example.hub/.well-known/mercure",
			token: "jwt-token",
			httpClient: new Shuttle(
				handler: new MockHandler([
					new Response(
						ResponseStatus::OK,
						"urn:uuid:dc3ed6b1-582e-4e0d-976a-2d8c5657c63d",
						["Content-Type" => "text/plain"]
					)
				])
			)
		);

		$receipt = $publisher->publish(
			new Message("fruits", "bananas")
		);

		$this->assertEquals(
			"urn:uuid:dc3ed6b1-582e-4e0d-976a-2d8c5657c63d",
			$receipt
		);
	}
}