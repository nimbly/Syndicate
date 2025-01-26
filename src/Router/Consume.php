<?php

namespace Nimbly\Syndicate\Router;

use Attribute;

#[Attribute]
class Consume
{
	/**
	 * @param string|array<string> $topic The topic or topics to match. You can provide multiple topics and are ORed.
	 * @param array<string,string|array<string>> $payload The JSON path statements to match. You can provide multiple JSON paths and values, matches are ANDed.
	 * @param array<string,string|array<string>> $attributes The attributes to match. If providing multiple values for an attribute, matches are ORed.
	 * @param array<string,string|array<string>> $headers The headers to match.  If providing multiple values for a header, matches are ORed.
	 */
	public function __construct(
		protected string|array $topic = [],
		protected array $payload = [],
		protected array $attributes = [],
		protected array $headers = [])
	{
	}

	/**
	 * Get the topic(s) to match on the Message.
	 *
	 * @return string|array<string>
	 */
	public function getTopic(): string|array
	{
		return $this->topic;
	}

	/**
	 * Get the payload JSON paths to match on the Message.
	 *
	 * @return array<string,string|array<string>>
	 */
	public function getPayload(): array
	{
		return $this->payload;
	}

	/**
	 * Get the attributes to match on the Message.
	 *
	 * @return array<string,string|array<string>>
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * Get the headers to match on the Message.
	 *
	 * @return array<string,string|array<string>>
	 */
	public function getHeaders(): array
	{
		return $this->headers;
	}
}