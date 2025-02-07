<?php

namespace Nimbly\Syndicate\Adapter\PubSub;

use Throwable;
use Aws\Sns\SnsClient;
use Nimbly\Syndicate\Message;
use Aws\Exception\CredentialsException;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\PublishException;

class Sns implements PublisherInterface
{
	/**
	 * @param SnsClient $client
	 * @param string|null $base_arn An optional base ARN if you are publishing all messages to the same AWS account. With this option set, when you publish a message, its topic does not need to include the base ARN portion.
	 */
	public function __construct(
		protected SnsClient $client,
		protected ?string $base_arn = null
	)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * Message attributes:
	 * * `MessageGroupId` (string)
	 * * `MessageDeduplicationId (string)
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$args = $this->buildArguments($message, $options);

		try {

			$result = $this->client->publish($args);
		}
		catch( CredentialsException $exception ){
			throw new ConnectionException(
				message: "Connection to SNS failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new PublishException(
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
		$args = \array_filter([
			"TopicArn" => $this->base_arn ?? "" . $message->getTopic(),
			"Data" => $message->getPayload(),
			"MessageGroupId" => $message->getAttributes()["MessageGroupId"] ?? null,
			"MessageDeduplicationId" => $message->getAttributes()["MessageDeduplicationId"] ?? null,
			...$options,
		]);

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