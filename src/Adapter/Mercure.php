<?php

namespace Nimbly\Syndicate\Adapter;

use Nimbly\Capsule\HttpMethod;
use Nimbly\Capsule\Request;
use Nimbly\Shuttle\Shuttle;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Message;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\RequestExceptionInterface;

class Mercure implements PublisherInterface
{
	public function __construct(
		protected string $hub,
		protected string $token,
		protected ClientInterface $httpClient = new Shuttle
	)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * Message attributes:
	 *	* `id` (string) Unique identifier for the message.
	 *	* `private` (boolean) Private event.
	 *	* `type` (string) The SSE event type.
	 *
	 * Options:
	 *	* `retry` (string) Retry/reconnection timeout.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$body = \array_filter([
			"topic" => $message->getTopic(),
			"data" => $message->getPayload(),
			"id" => $message->getAttributes()["id"] ?? null,
			"private" => (bool) ($message->getAttributes()["private"] ?? false),
			"type" => $message->getAttributes()["type"] ?? null,
			"retry" => $options["retry"] ?? null,
		]);

		$request = new Request(
			method: HttpMethod::POST,
			uri: $this->hub,
			headers: [
				"Content-Type" => "application/x-www-form-urlencoded",
				"Authorization" => "Bearer " . $this->token,
			],
			body: \http_build_query($body),
		);

		try {

			$response = $this->httpClient->sendRequest($request);
		}
		catch( RequestExceptionInterface $exception ){
			throw new ConnectionException(
				message: "Failed to connect to Mercure hub.",
				previous: $exception
			);
		}

		if( $response->getStatusCode() !== 200 ){
			throw new PublishException(
				message: \sprintf(
					"Failed to publish message: %s %s.",
					$response->getStatusCode(),
					$response->getReasonPhrase()
				)
			);
		}

		return $response->getBody()->getContents();
	}
}