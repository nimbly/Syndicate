<?php

namespace Nimbly\Syndicate;

interface ValidatorInterface
{
	/**
	 * Validate a Message conforms to schema.
	 *
	 * @param Message $message The message instance to validate.
	 * @throws MessageValidationException
	 * @return boolean Always returns `true`. If message is not valid, a `MessageValidationException` will be thrown.
	 */
	public function validate(Message $message): bool;
}