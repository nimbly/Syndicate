<?php

namespace Nimbly\Syndicate\Validator;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Exception\MessageValidationException;

interface ValidatorInterface
{
	/**
	 * Validate a Message conforms to schema.
	 *
	 * @param Message $message The message instance to validate.
	 * @throws MessageValidationException
	 * @return boolean Should always returns `true`. If message is not valid, a `MessageValidationException` should be thrown.
	 */
	public function validate(Message $message): bool;
}