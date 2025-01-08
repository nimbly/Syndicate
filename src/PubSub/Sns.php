<?php

namespace Nimbly\Syndicate\PubSub;

use Throwable;
use Aws\Sns\SnsClient;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;

class Sns implements PublisherInterface
{
	public function __construct(
		protected SnsClient $client
	)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * * `MessageGroupId` (string)
	 * * `MessageDeduplicationId (string)
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$args = $this->buildArguments($message, $options);

		try {

			$result = $this->client->publish($args);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return $result->get("MessageId");
	}

	/**
	 * Build the arguments array needed to call SNS.
	 *
	 * @param Message $message
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>
	 */
	private function buildArguments(Message $message, array $options = []): array
	{
		$args = [
			"TopicArn" => $message->getTopic(),
			"Data" => $message->getPayload(),
			...$options,
		];

		if( $message->getAttributes() ){
			$args["MessageAttributes"] = $message->getAttributes();
		}

		if( isset($options["MessageGroupId"]) ){
			$args["MessageGroupId"] = $options["MessageGroupId"];
		}

		if( isset($options["MessageDeduplicationId"]) ){
			$args["MessageDeduplicationId"] = $options["MessageDeduplicationId"];
		}

		return $args;
	}
}