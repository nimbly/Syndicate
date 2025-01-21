<?php

namespace Nimbly\Syndicate\Middleware;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\MiddlewareInterface;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\Response;

/**
 * This middleware can be added to your `Application` instance and allows you
 * to move loop based consumer messages to a deadletter location. Simply
 * return a `Response::deadletter` from your handlers and this middleware will
 * take care of the rest. If you are *not* using a loop based consumer
 * (`PubSub\Mqtt` or `PubSub\Redis`), you *do not* need this middleware as
 * deadlettering is supported directly.
 */
class DeadletterMessage implements MiddlewareInterface
{
	public function __construct(
		protected PublisherInterface $deadletter,
	)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Message $message, callable $next): mixed
	{
		$response = $next($message);

		if( $response === Response::deadletter ){
			$this->deadletter->publish($message);
			return Response::ack;
		}

		return $response;
	}
}