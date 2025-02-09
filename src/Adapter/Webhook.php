<?php

namespace Nimbly\Syndicate\Adapter;

use Throwable;
use Psr\Http\Client\ClientInterface;
use Nimbly\Capsule\HttpMethod;
use Nimbly\Capsule\Request;
use Nimbly\Syndicate\Message;
use Nimbly\Capsule\ResponseStatus;
use Nimbly\Shuttle\Shuttle;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\PublishException;

/**
 * A simple HTTP Webhook publisher.
 *
 * This publisher will make HTTP calls to the given hostname and endpoint. It assumes
 * the endpoint will be the topic name and will make a POST call.
 *
 * You can supply a default set of headers to be included with each HTTP request.
 *
 * Alternatively, you can override the
 */
class Webhook implements PublisherInterface
{
	/**
	 * @param ClientInterface $httpClient PSR-18 HTTP ClientInterface instance. Defaults to a `Nimbly\Shuttle` instance.
	 * @param string|null $hostname Default/base hostname/uri to send HTTP requests to.
	 * @param array<string,string> $headers Default headers to send with each request.
	 * @param HttpMethod $method Default HTTP method to use. Defaults to `HttpMethod::POST`.
	 */
	public function __construct(
		protected ClientInterface $httpClient = new Shuttle,
		protected ?string $hostname = null,
		protected array $headers = [],
		protected HttpMethod $method = HttpMethod::POST
	)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `method` (string) Override the default HTTP method to use.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$response = $this->httpClient->sendRequest(
				$this->buildRequest($message, $options)
			);
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

		return $response->getBody()->getContents();
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
		if( \preg_match("/^https?:\/\//i", $message->getTopic()) ){
			$uri = $message->getTopic();
		}
		else {
			$uri = ($this->hostname ?? "") . $message->getTopic();
		}

		return new Request(
			method: $options["method"] ?? $this->method,
			uri: $uri,
			body: $message->getPayload(),
			headers: \array_merge(
				$this->headers,
				$message->getHeaders()
			)
		);
	}
}