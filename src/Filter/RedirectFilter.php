<?php

namespace Nimbly\Syndicate\Filter;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherInterface;

/**
 * This filter redirects messages to the topic you provide in the
 * filter rather than the topic provided in the Message instance.
 *
 * Some use cases are:
 * 	- Publishing a Message to a deadletter location under a different topic or queue URL/name.
 */
class RedirectFilter implements PublisherInterface
{
	/**
	 * @param PublisherInterface $publisher The publisher instance to publish deadletter messages to.
	 * @param string $topic The topic name, queue name, queue URL, or location to publish deadletter messages to. This is highly dependent on the Publisher being used.
	 */
	public function __construct(
		protected PublisherInterface $publisher,
		protected string $topic,
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$receipt = $this->publisher->publish(
			message: new Message(
				topic: $this->topic,
				payload: $message->getPayload(),
				attributes: $message->getAttributes(),
				headers: $message->getHeaders()
			),
			options: $options
		);

		return $receipt;
	}
}