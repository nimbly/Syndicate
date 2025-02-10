<?php

namespace Nimbly\Syndicate\Adapter;

use Nimbly\Syndicate\Message;

/**
 * This adapter sends your messages into the void never
 * to be seen again. This adapter may make sense if you are
 * developing your application locally and do not care about or
 * need messages to be published out to other services.
 */
class NullPublisher implements PublisherInterface
{
	/**
	 * @param callable|null $receipt A callback that can generate a receipt when publishing.
	 */
	public function __construct(
		protected $receipt = null
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		if( \is_callable($this->receipt) ){
			return \call_user_func($this->receipt, $message);
		}

		return \bin2hex(\random_bytes(12));
	}
}