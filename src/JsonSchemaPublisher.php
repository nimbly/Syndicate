<?php

namespace Nimbly\Syndicate;

use Opis\JsonSchema\Validator;

class JsonSchemaPublisher implements PublisherInterface
{
	protected Validator $validator;

	/**
	 * @param PublisherInterface $publisher The publisher to send messages to.
	 * @param array<string> $schemas A key/value pair array of topic names to JSON schemas.
	 */
	public function __construct(
		protected PublisherInterface $publisher,
		protected array $schemas = [],
	)
	{
		$this->validator = new Validator;
		$this->validator->setStopAtFirstError(false);
	}

	/**
	 * @inheritDoc
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		if( !isset($this->schemas[$message->getTopic()]) ){
			throw new MessageValidationException(
				\sprintf(
					"No schema defined for message topic \"%s\".",
					$message->getTopic()
				),
				$message
			);
		}

		$result = $this->validator->validate(
			\json_decode($message->getPayload()),
			$this->schemas[$message->getTopic()]
		);

		if( $result->hasError() ){
			throw new MessageValidationException(
				"Message failed JSON schema validation.",
				$message,
				$result->error()
			);
		}

		return $this->publisher->publish($message, $options);
	}
}