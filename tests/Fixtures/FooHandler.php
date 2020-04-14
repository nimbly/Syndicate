<?php

namespace Syndicate\Tests\Fixtures;

use DateTime;
use Syndicate\Message;

class FooHandler
{
	protected $dateTime;

	public function __construct(DateTime $dateTime)
	{
		$this->dateTime = $dateTime;
	}

	public function onFooCreated(Message $message): void
	{
		$message->delete();
	}
}