<?php

namespace Nimbly\Syndicate\Validator;

use Exception;
use Nimbly\Syndicate\Message;

/**
 * This exception is thrown when a Message has failed validation.
 */
class MessageValidationException extends Exception
{
	public function __construct(
		string $message,
		protected Message $failedMessage,
		protected array $context = [],
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

	/**
	 * Get addtional context on the validation error that
	 * includes a more specific error message, the offending
	 * data, and full path to that data.
	 *
	 * @return array{message:string,path:string,data:mixed}
	 */
	public function getContext(): array
	{
		return $this->context;
	}
}