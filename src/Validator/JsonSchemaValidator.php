<?php

namespace Nimbly\Syndicate\Validator;

use Nimbly\Syndicate\Message;
use Opis\JsonSchema\Validator;

class JsonSchemaValidator implements ValidatorInterface
{
	protected Validator $validator;

	/**
	 * @param array<string,string> $schemas A key/value pair array of topic names to JSON schemas.
	 * @param bool $ignore_missing_schemas If a schema cannot be found for a Message topic, should validation be ignored? Defaults to `false`.
	 */
	public function __construct(
		protected array $schemas = [],
		protected bool $ignore_missing_schemas = false
	)
	{
		$this->validator = new Validator;
		$this->validator->setStopAtFirstError(false);
	}

	/**
	 * @inheritDoc
	 * @throws MessageValidationException
	 */
	public function validate(Message $message): bool
	{
		if( !isset($this->schemas[$message->getTopic()]) ){

			if( $this->ignore_missing_schemas ){
				return true;
			}

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

		return true;
	}
}