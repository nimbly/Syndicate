<?php

namespace Nimbly\Syndicate\Validator;

use Exception;
use Nimbly\Syndicate\Message;

/**
 * This exception is thrown when a Message has failed validation when
 * using the JsonSchemaPublisher.
 */
class MessageValidationException extends Exception
{
	public function __construct(
		string $message,
		protected Message $failedMessage
	)
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
}