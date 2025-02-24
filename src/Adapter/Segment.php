<?php

namespace Nimbly\Syndicate\Adapter;

use Segment\Client;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Message;

/**
 * Segment.io adapter supporting "track", "identify", and "group" calls.
 *
 * @see https://segment.com/docs/connections/spec
 */
class Segment implements PublisherInterface
{
	/**
	 * @param Client $client Segment client.
	 * @param boolean $autoflush If you would rather flush queued messages manually, set this to `false`.
	 */
	public function __construct(
		protected Client $client,
		protected bool $autoflush = true,
	)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * NOTE: The Message topic name is the Segment call to make: `track`, `identify`, and `group`.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$result = match( $message->getTopic() ){
			"track" 	=> 	$this->client->track(
								$this->buildTrackRequest($message)
							),

			"identify" 	=> 	$this->client->identify(
								$this->buildIdentifyRequest($message)
							),

			"group"		=>	$this->client->group(
								$this->buildGroupRequest($message)
							),

			default => throw new PublishException(
				message: \sprintf(
					"Unknown or unsupported Segment call %s.",
					$message->getTopic()
				)
			)
		};

		if( $result === false ){
			throw new PublishException(
				message: "Failed to publish message."
			);
		}

		if( $this->autoflush ){
			$this->client->flush();
		}

		return null;
	}

	/**
	 * Build the base/common request elements for all Segment actions.
	 *
	 * @param Message $message
	 * @return array
	 */
	protected function buildCommonRequest(Message $message): array
	{
		$request = \array_filter([
			"anonymousId" => $message->getAttributes()["anonymousId"] ?? null,
			"userId" => $message->getAttributes()["userId"] ?? null,
			"integrations" => $message->getAttributes()["integrations"] ?? [],
			"timestamp" => $message->getAttributes()["timestamp"] ?? null,
			"context" => $message->getAttributes()["context"] ?? null,
		]);

		if( !isset($request["anonymousId"]) && !isset($request["userId"]) ){
			throw new PublishException(
				message: "Segment requires an anonymous ID or a user ID. Please add either an \"anonymousId\" or \"userId\" to the message attributes."
			);
		}

		return $request;
	}

	/**
	 * Build the request needed to make a track call.
	 *
	 * @param Message $message
	 * @return array
	 */
	protected function buildTrackRequest(Message $message): array
	{
		$request = \array_merge(
			$this->buildCommonRequest($message),
			[
				"event" => $message->getAttributes()["event"] ?? null,
				"properties" => \json_decode($message->getPayload(), true),
			]
		);

		if( !isset($request["event"]) ){
			throw new PublishException(
				message: "Segment track call requires an event name. Please add an \"event\" attribute to the message."
			);
		}

		return $request;
	}

	/**
	 * Build the request needed to make an Identify call.
	 *
	 * @param Message $message
	 * @return array
	 */
	protected function buildIdentifyRequest(Message $message): array
	{
		$request = \array_merge(
			$this->buildCommonRequest($message),
			[
				"traits" => \json_decode($message->getPayload(), true),
			]
		);

		return $request;
	}

	/**
	 * Build the request needed to make a Group call.
	 *
	 * @param Message $message
	 * @return array
	 */
	protected function buildGroupRequest(Message $message): array
	{
		if( !isset($message->getAttributes()["groupId"]) ){
			throw new PublishException(
				message: "Segment group call requires a groupId. Please add a \"groupId\" attribute to the message."
			);
		}

		$request = \array_merge(
			$this->buildCommonRequest($message),
			[
				"groupId" => $message->getAttributes()["groupId"],
				"traits" => \json_decode($message->getPayload(), true),
			]
		);

		return $request;
	}
}