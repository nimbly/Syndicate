<?php

namespace Nimbly\Syndicate\Middleware;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\MiddlewareInterface;
use Nimbly\Syndicate\ValidatorInterface;

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
		$this->validator->validate($message);
		return $next($message);
	}
}