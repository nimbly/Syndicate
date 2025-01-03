<?php

namespace Nimbly\Syndicate;

class Message
{
	/**
	 * @param string $topic
	 * @param string $payload
	 * @param array<string,mixed> $attributes
	 * @param array<string,mixed> $headers
	 * @param mixed $reference
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
	 * The topic this Message is intended for.
	 *
	 * @return string
	 */
	public function getTopic(): ?string
	{
		return $this->topic;
	}

	/**
	 * The raw payload from the event.
	 *
	 * @return string
	 */
	public function getPayload(): string
	{
		return $this->payload;
	}

	/**
	 * Message attributes that can be passed on.
	 *
	 * @return array<string,mixed>
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * Message headers
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
