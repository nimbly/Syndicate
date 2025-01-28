<?php

namespace Nimbly\Syndicate\Validator;

use Nimbly\Syndicate\Message;
use Opis\JsonSchema\Errors\ValidationError;

/**
 * This exception is thrown when a Message has failed validation when
 * using the JsonSchemaValidator.
 */
class JsonSchemaValidationException extends MessageValidationException
{
	public function __construct(
		string $message,
		Message $failedMessage,
		protected ?ValidationError $validationError = null)
	{
		parent::__construct($message, $failedMessage);
	}

	/**
	 * Get the Opis ValidationError instance.
	 *
	 * This instance contains details on what and where the Message failed validation.
	 *
	 * @return ValidationError|null
	 */
	public function getValidationError(): ?ValidationError
	{
		return $this->validationError;
	}
}