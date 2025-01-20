<?php

namespace Nimbly\Syndicate;

use Exception;
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
	 * Get the Opis ValidationError,
	 *
	 * @return ValidationError|null
	 */
	public function getValidationError(): ?ValidationError
	{
		return $this->validationError;
	}
}