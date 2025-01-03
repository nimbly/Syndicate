<?php

namespace Nimbly\Syndicate;

use Nimbly\Syndicate\PublisherException;

interface PublisherInterface
{
	/**
	 * Publish a message.
	 *
	 * @param Message $message
	 * @param array<string,mixed> $options
	 * @throws PublisherException
	 * @return string|null Some publishers return a receipt or confirmation identifier.
	 */
	public function publish(Message $message, array $options = []): ?string;
}