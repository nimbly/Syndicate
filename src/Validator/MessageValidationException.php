<?php

namespace Nimbly\Syndicate\Validator;

use Exception;
use Nimbly\Syndicate\Message;
use Opis\JsonSchema\Errors\ValidationError;

/**
 * This exception is thrown when a Message has failed validation when
 * using the JsonSchemaPublisher.
 */
class MessageValidationException extends Exception
{
	public function __construct(
		string $message,
		protected Message $failedMessage,
		protected ?ValidationError $validationError = null)
	{
		parent::__construct($message);
	}

	/**
	 * Get the Message that failed validation.
	 *
	 * @return Message
	 */
	public function getFailedMessage(): Message
	{
		return $this->failedMessage;
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