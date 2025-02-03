<?php

namespace Nimbly\Syndicate\Middleware;

use Nimbly\Syndicate\Message;

interface MiddlewareInterface
{
	/**
	 * Handle a Message instance and pass to the `next` layer in the
	 * middleware chain.
	 *
	 * @param Message $message
	 * @param callable $next
	 * @return mixed
	 */
	public function handle(Message $message, callable $next): mixed;
}