<?php

namespace Nimbly\Syndicate\Middleware;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use Nimbly\Syndicate\Adapter\PublisherInterface;

/**
 * This middleware can be added to your `Application` instance and allows you
 * to move subcriber based consumed messages to a deadletter location. Simply
 * return a `Response::deadletter` from your handlers and this middleware will
 * take care of the rest. If you are *not* using a subscriber based consumer
 * (`PubSub\Gearman`, `PubSub\Mqtt`, or `PubSub\Redis`), you *do not* need this
 * middleware as deadlettering is supported directly.
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