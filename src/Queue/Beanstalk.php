<?php

namespace Nimbly\Syndicate\Queue;

use Nimbly\Syndicate\ConsumerException;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Nimbly\Syndicate\Message;
use Pheanstalk\PheanstalkInterface;
use Nimbly\Syndicate\ConsumerInterface;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Throwable;

class Beanstalk implements PublisherInterface, ConsumerInterface
{
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
	 * 	* `priority` (integer) Priority level, defaults to `PheanstalkInterface::DEFAULT_DELAY` (1024).
	 * 	* `delay` (integer) Delay in seconds before message becomes available.
	 * 	* `ttr` (integer) Time to run, in seconds. Amount of time message may be resevered for.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		try {

			$job_id = $this->client->putInTube(
				$message->getTopic(),
				$message->getPayload(),
				$options["priority"] ?? PheanstalkInterface::DEFAULT_PRIORITY,
				$options["delay"] ?? PheanstalkInterface::DEFAULT_DELAY,
				$options["ttr"] ??  PheanstalkInterface::DEFAULT_TTR
			);
		}
		catch( Throwable $exception ){
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return (string) $job_id;
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `timeout` (integer) Polling timeout in seconds.
	 */
	public function consume(string $topic, int $max_messages = 1, array $options = []): array
	{
		$result = [];

		for( $i = 0; $i < $max_messages; $i++){

			try {
				/**
				 * @var Job|null $job
				 */
				$job = $this->client->reserveFromTube(
					$topic,
					$options["timeout"] ?? null
				);
			}
			catch( Throwable $exception ){
				throw new ConsumerException(
					message: "Failed to consume message.",
					previous: $exception
				);
			}

			if( !empty($job) ){
				$result[] = new Message(
					topic: $topic,
					payload: $job->getData(),
					reference: $job
				);
			}
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function ack(Message $message): void
	{
		try {

			$this->client->delete($message->getReference());
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
				$message->getReference(),
				PheanstalkInterface::DEFAULT_PRIORITY,
				$timeout
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