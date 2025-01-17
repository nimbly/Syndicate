<?php

namespace Nimbly\Syndicate;

interface PublisherInterface
{
	/**
	 * Publish a message.
	 *
	 * @param Message $message The message to publish.
	 * @param array<string,mixed> $options A key/value pair of implementation specific options when publishing.
	 * @throws ConnectionException
	 * @throws PublisherException
	 * @return string|null Some publishers return a receipt or confirmation identifier.
	 */
	public function publish(Message $message, array $options = []): ?string;
}