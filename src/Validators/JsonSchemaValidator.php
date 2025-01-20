<?php

namespace Nimbly\Syndicate\Validators;

use Nimbly\Syndicate\Message;
use Opis\JsonSchema\Validator;
use Nimbly\Syndicate\ValidatorInterface;
use Nimbly\Syndicate\MessageValidationException;

class JsonSchemaValidator implements ValidatorInterface
{
	protected Validator $validator;

	/**
	 * @param array<string,string> $schemas A key/value pair array of topic names to JSON schemas.
	 */
	public function __construct(
		protected array $schemas = [],
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