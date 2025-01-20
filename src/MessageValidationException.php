<?php

namespace Nimbly\Syndicate;

use Exception;
use Opis\JsonSchema\Errors\ValidationError;

class MessageValidationException extends Exception
{
	public function __construct(
		string $message,
		protected Message $failedMessage,
		protected ?ValidationError $validationError = null)
	{
		parent::__construct($message);
	}

	public function getFailedMessage(): Message
	{
		return $this->failedMessage;
	}

	public function getValidationError(): ?ValidationError
	{
		return $this->validationError;
	}
}