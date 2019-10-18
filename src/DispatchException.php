<?php

namespace Syndicate;

use Exception;
use Syndicate\Message;

class DispatchException extends Exception
{
	/**
	 * Message instance.
	 *
	 * @var Message|null
	 */
	protected $queueMessage;

	/**
	 * Set the Message instance that could not be dispatched.
	 *
	 * @param Message $message
	 * @return void
	 */
	public function setQueueMessage(Message $message): void
	{
		$this->queueMessage = $message;
	}

	/**
	 * Get the Message instance that could be dispatched.
	 *
	 * @return Message|null
	 */
	public function getQueueMessage(): ?Message
	{
		return $this->queueMessage;
	}
}