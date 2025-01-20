<?php

namespace Nimbly\Syndicate;

class ValidatorPublisher implements PublisherInterface
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
		$this->validator->validate($message);

		return $this->publisher->publish($message, $options);
	}
}