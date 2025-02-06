<?php

namespace Nimbly\Syndicate\Adapter\PubSub;

use Nimbly\Capsule\HttpMethod;
use Nimbly\Capsule\Request;
use Nimbly\Shuttle\Shuttle;
use Nimbly\Syndicate\Adapter\PublisherInterface;
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
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$body = \array_filter([
			"topic" => $message->getTopic(),
			"data" => $message->getPayload(),
			"id" => $message->getAttributes()["id"] ?? null,
			"private" => $message->getAttributes()["private"] ?? null,
			"type" => $message->getAttributes()["type"] ?? null,
		]);

		$request = new Request(
			method: HttpMethod::POST,
			uri: $this->hub,
			headers: [
				"Authorization" => "Bearer " . $this->token
			],
			body: \http_build_query($body),
		);

		try {

			$response = $this->httpClient->sendRequest($request);
		}
		catch( RequestExceptionInterface $exception ){
			throw new PublishException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		if( $response->getStatusCode() >= 400 ){
			throw new PublishException(
				message: "Failed to publish message."
			);
		}

		return $response->getBody()->getContents();
	}
}