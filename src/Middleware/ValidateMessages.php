<?php

namespace Nimbly\Syndicate\Middleware;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use Nimbly\Syndicate\ValidatorInterface;
use Nimbly\Syndicate\MiddlewareInterface;
use Nimbly\Syndicate\MessageValidationException;

/**
 * This middleware can be added to your `Application` instance and provides
 * validation of Messages that are consumed. If a Message does not validate,
 * a `Response::deadletter` is returned in hopes of sending the message to
 * your defined deadletter location.
 */
class ValidateMessages implements MiddlewareInterface
{
	public function __construct(
		protected ValidatorInterface $validator
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Message $message, callable $next): mixed
	{
		try {

			$this->validator->validate($message);
		}
		catch( MessageValidationException ){
			return Response::deadletter;
		}

		return $next($message);
	}
}