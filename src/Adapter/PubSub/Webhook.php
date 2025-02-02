<?php

namespace Nimbly\Syndicate\Adapter\PubSub;

use Nimbly\Capsule\Request;
use Nimbly\Syndicate\Message;
use Nimbly\Capsule\HttpMethod;
use Nimbly\Capsule\ResponseStatus;
use Psr\Http\Client\ClientInterface;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\PublishException;
use Throwable;

/**
 * A simple Webhook publisher.
 *
 * This publisher will make HTTP calls to the given hostname and endpoint. It assumes the endpoint will be the
 * topic name and will make a POST call.
 *
 * You can supply a default set of headers to be included with each HTTP request.
 *
 * Alternatively, you can override the default assumptions by using the `$options` parameter when
 * calling `publish`.
 */
class Webhook implements PublisherInterface
{
	public function __construct(
		protected ClientInterface $client,
		protected ?string $hostname = null,
		protected array $headers = []
	)
	{
	}

	/**
	 * @inheritDoc
	 * @return null
	 *
	 * Options:
	 * 	* `method` (string) Override the default (POST) HTTP method to use.
	 *  * `uri` (string) Override the URI being used.
	 *  * `headers` (array<string,string>) Additional headers to be included with the request. These are merged with default headers provided in constructor as well as headers within the `Message` instance itself.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$response = $this->client->sendRequest($this->buildRequest($message, $options));
		}
		catch( Throwable $exception ){
			throw new ConnectionException(
				message: "Failed to connect to webhook remote host.",
				previous: $exception
			);
		}

		if( $response->getStatusCode() >= ResponseStatus::BAD_REQUEST->value ){
			throw new PublishException(
				message: "Failed to publish message.",
				code: $response->getStatusCode()
			);
		}

		return null;
	}

	/**
	 * Build the Request instance to send as webhook.
	 *
	 * @param Message $message
	 * @param array<string,mixed> $options
	 * @return Request
	 */
	protected function buildRequest(Message $message, array $options = []): Request
	{
		return new Request(
			method: $options["method"] ?? HttpMethod::POST,
			uri: $options["uri"] ??
				\sprintf(
					"%s/%s",
					\trim($this->hostname ?? "", "/"),
					\urlencode($message->getTopic())
				),
			body: $message->getPayload(),
			headers: \array_merge(
				$this->headers,
				$options["headers"] ?? [],
				$message->getHeaders()
			)
		);
	}
}