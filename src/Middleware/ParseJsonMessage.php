<?php

namespace Nimbly\Syndicate\Middleware;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;

/**
 * This middleware will automatically parse your message payloads as JSON
 * and set the parsed payload on the Message. The parsed payload can be
 * retrieved via the `getParsedPayload()` method on the `Message` instance.
 *
 * If the payload cannot be parsed, the message will attempted to be deadlettered.
 */
class ParseJsonMessage implements MiddlewareInterface
{
	/**
	 * @param boolean $associative Parse payload as an associative array instead of an object. Defaults to `false`.
	 */
	public function __construct(
		protected bool $associative = false
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Message $message, callable $next): mixed
	{
		$parsed_payload = \json_decode($message->getPayload(), $this->associative);

		if( \json_last_error() !== JSON_ERROR_NONE ){
			return Response::deadletter;
		}

		$message->setParsedPayload($parsed_payload);

		return $next($message);
	}
}