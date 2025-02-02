<?php

namespace Nimbly\Syndicate\Adapter;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;

interface PublisherInterface
{
	/**
	 * Publish a message.
	 *
	 * @param Message $message The message to publish.
	 * @param array<string,mixed> $options A key/value pair of implementation specific options when publishing.
	 * @throws ConnectionException
	 * @throws PublishException
	 * @return string|null Some publishers return a receipt or confirmation identifier.
	 */
	public function publish(Message $message, array $options = []): ?string;
}