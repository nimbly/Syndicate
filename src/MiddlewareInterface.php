<?php

namespace Nimbly\Syndicate;

interface MiddlewareInterface
{
	public function handle(Message $message, callable $next): mixed;
}