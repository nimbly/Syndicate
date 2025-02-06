<?php

namespace Nimbly\Syndicate\Adapter\PubSub;

use Segment\Client;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\PublishException;

class Segment implements PublisherInterface
{
	/**
	 * @param Client $client Segment Client instance.
	 * @param boolean $auto_flush Automatically send (flush) messages with each call to `publish`. By default, Segment queues the messages to be sent until you call `Client::flush()` or set the `flush_at` option when creating the `Client` instance.
	 */
	public function __construct(
		protected Client $client,
		protected bool $auto_flush = true
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$msg["properties"] = \json_decode($message->getParsedPayload());
		$msg["context"] = $message->getAttributes();

		$result = \call_user_func([$this->client, $message->getTopic()], $msg);

		if( $result === false ){
			throw new PublishException(
				message: "Failed to publish message."
			);
		}

		if( $this->auto_flush ){
			$this->client->flush();
		}

		return null;
	}
}