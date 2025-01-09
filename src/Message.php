<?php

namespace Nimbly\Syndicate;

class Message
{
	/**
	 * @param string $topic The topic or queue name/URL to publish this message to.
	 * @param string $payload The payload (or body) of the message.
	 * @param array<string,mixed> $attributes A key/value pair of attributes to be sent with message. Most implementations do not support attributes.
	 * @param array<string,mixed> $headers A key/value pair of headers to be sent with message. Most implementations do not support headers.
	 * @param mixed $reference A reference to the original source message. This is populated when pulling messages off source.
	 */
	public function __construct(
		protected string $topic,
		protected string $payload,
		protected array $attributes = [],
		protected array $headers = [],
		protected mixed $reference = null,
	)
	{
	}

	/**
	 * The topic, queue name, or queue URL this Message is intended for or came from.
	 *
	 * @return string
	 */
	public function getTopic(): string
	{
		return $this->topic;
	}

	/**
	 * The raw payload/body of the message.
	 *
	 * @return string
	 */
	public function getPayload(): string
	{
		return $this->payload;
	}

	/**
	 * Message attributes.
	 *
	 * @return array<string,mixed>
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * Message headers.
	 *
	 * @return array<string,mixed>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * Get the reference to the original message.
	 *
	 * @return mixed Depending on the implementation, this could be a string, an array of values, or the full original message object.
	 */
	public function getReference(): mixed
	{
		return $this->reference;
	}
}
