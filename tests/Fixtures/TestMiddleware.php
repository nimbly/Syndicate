<?php

namespace Nimbly\Syndicate\Tests\Fixtures;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Middleware\MiddlewareInterface;

class TestMiddleware implements MiddlewareInterface
{
	public function handle(Message $message, callable $next): mixed
	{
		return $next($message);
	}
}