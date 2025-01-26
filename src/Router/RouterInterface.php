<?php

namespace Nimbly\Syndicate\Router;

use Nimbly\Syndicate\Message;

interface RouterInterface
{
	/**
	 * Resolve a Message into a callable handler.
	 *
	 * @param Message $message The message to resolve.
	 * @return callable|string|null A callable, or a string in the format "Fully\Qualified\Namespace\ClassName@method", or null if no match could be found.
	 */
	public function resolve(Message $message): callable|string|null;
}