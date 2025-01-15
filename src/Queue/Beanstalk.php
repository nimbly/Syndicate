<?php

namespace Nimbly\Syndicate\Queue;

use Throwable;
use Pheanstalk\Pheanstalk;
use Nimbly\Syndicate\Message;
use Pheanstalk\Values\TubeName;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\ConnectionException;
use Pheanstalk\Exception\ConnectionException as PheanstalkConnectionException;

class Beanstalk implements PublisherInterface, ConsumerInterface
{
	protected ?TubeName $consumer_tube = null;

	/**
	 * @param Pheanstalk $client
	 */
	public function __construct(
		protected Pheanstalk $client)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `priority` (integer) Priority level, defaults to `Pheanstalk::DEFAULT_DELAY` (1024).
	 * 	* `delay` (integer) Delay in seconds before message becomes available.
	 * 	* `time_to_release` (integer) Time to run, in seconds. Amount of time message may be resevered for.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$this->client->useTube(new TubeName($message->getTopic()));

			$job = $this->client->put(
				data: $message->getPayload(),
				priority: $options["priority"] ?? Pheanstalk::DEFAULT_PRIORITY,
				delay: $options["delay"] ?? Pheanstalk::DEFAULT_DELAY,
				timeToRelease: $options["time_to_release"] ??  Pheanstalk::DEFAULT_TTR
			);
		}
		catch( PheanstalkConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Beanstalkd failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return $job->getId();
	}

	/**
	 * @inheritDoc
	 *
	 * Beanstalk does not allow any more than a single message to be reserved at a time. Setting
	 * the `max_messages` argument will always result in a maximum of one message to be reserved.
	 *
	 * Options:
	 * 	* `timeout` (integer) Polling timeout in seconds. Defaults to 10.
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		if( empty($this->consumer_tube) ){
			$this->client->watch(new TubeName($topic));
		}

		try {

			$job = $this->client->reserveWithTimeout(
				timeout: $options["timeout"] ?? 10
			);
		}
		catch( PheanstalkConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Beanstalkd failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		if( empty($job) ){
			return [];
		}

		return [
			new Message(
				topic: $topic,
				payload: $job->getData(),
				reference: $job
			)
		];
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		try {

			$this->client->delete(
				job: $message->getReference()
			);
		}
		catch( PheanstalkConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Beanstalkd failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to ack message.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function nack(Message $message, int $timeout = 0): void
	{
		try {

			$this->client->release(
				job: $message->getReference(),
				priority: Pheanstalk::DEFAULT_PRIORITY,
				delay: $timeout
			);
		}
		catch( PheanstalkConnectionException $exception ){
			throw new ConnectionException(
				message: "Connection to Beanstalkd failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to nack message.",
				previous: $exception
			);
		}
	}
}