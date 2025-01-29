<?php

namespace Nimbly\Syndicate\Filter;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\ConnectionException;
use Nimbly\Syndicate\Validator\ValidatorInterface;
use Nimbly\Syndicate\Validator\MessageValidationException;

/**
 * Validate messages before they are published.
 *
 * Given a `ValidatorInterface` instance and a `PublisherInterface` instance,
 * this filter will first validate the Message using the supplied validator and,
 * if valid, will publish to the supplied publisher.
 *
 * If validation fails, a `MessageValidationException` is thrown.
 */
class ValidatorFilter implements PublisherInterface
{
	public function __construct(
		protected ValidatorInterface $validator,
		protected PublisherInterface $publisher,
	)
	{
	}

	/**
	 * @inheritDoc
	 * @throws MessageValidationException
	 * @throws ConnectionException
	 * @throws PublisherException
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		if( $this->validator->validate($message) === false ){
			throw new MessageValidationException(
				"Message failed validation.",
				$message
			);
		}

		return $this->publisher->publish($message, $options);
	}
}