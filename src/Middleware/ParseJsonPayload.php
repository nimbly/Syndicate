<?php

namespace Nimbly\Syndicate\Middleware;

use Nimbly\Syndicate\Message;
use UnexpectedValueException;

/**
 * This middleware will automatically parse your message payloads as JSON
 * and set the parsed payload on the Message. The parsed payload can be
 * retrieved via the `Message::getParsedPayload()` method.
 *
 * If the payload cannot be parsed, an `UnexpectedValueException` is thrown.
 */
class ParseJsonPayload implements MiddlewareInterface
{
	/**
	 * @param boolean $associative Parse payload as an associative array instead of an object.
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
			throw new UnexpectedValueException("Payload is not valid JSON.");
		}

		$message->setParsedPayload($parsed_payload);

		return $next($message);
	}
}