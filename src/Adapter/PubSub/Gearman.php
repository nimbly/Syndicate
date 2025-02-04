<?php

namespace Nimbly\Syndicate\Adapter\PubSub;

use GearmanClient;
use GearmanWorker;
use GearmanJob;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Adapter\SubscriberInterface;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\SubscriptionException;
use UnexpectedValueException;

/**
 * Gearman background publisher and consumer. This integration only
 * supports background jobs (normal, low, and high priorities.)
 */
class Gearman implements PublisherInterface, SubscriberInterface
{
	protected bool $running = false;

	/**
	 * @param GearmanClient|null $client Client instance, only neccessary if publishing messages.
	 * @param GearmanWorker|null $worker Worker instance, only neccessary if consuming messages.
	 */
	public function __construct(
		protected ?GearmanClient $client = null,
		protected ?GearmanWorker $worker = null)
	{
		if( empty($client) && empty($worker) ){
			throw new UnexpectedValueException(
				"Depending on your use case, you need either a GearmanClient or GearmanWorker instance. ".
				"If you are publishing new jobs, you will need a GearmanClient instance. ".
				"If you are consuming jobs, you will need a GearmanWorker instance."
			);
		}
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `priority` (string) The job priority. Values are `low`, `normal`, `high`. Defaults to `normal`.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		if( $this->client === null ){
			throw new PublishException(
				"No GearmanClient instance was given. ".
				"In order to publish new jobs, you must pass a GearmanClient instance ".
				"into the constructor."
			);
		}

		$job_id = match( $options["priority"] ?? "normal" ){
			"low" => $this->client->doLowBackground(
						$message->getTopic(),
						$message->getPayload()
					),

			"high" => $this->client->doHighBackground(
						$message->getTopic(),
						$message->getPayload()
					),

			default => $this->client->doBackground(
						$message->getTopic(),
						$message->getPayload()
					),
		};

		return $job_id;
	}

	/**
	 * @inheritDoc
	 */
	public function subscribe(string|array $topics, callable $callback, array $options = []): void
	{
		if( $this->worker === null ){
			throw new ConsumeException(
				"No GearmanWorker instance was given. ".
				"In order to process jobs, you must pass a GearmanWorker instance ".
				"into the constructor."
			);
		}

		if( !\is_array($topics) ){
			$topics = \array_map(
				fn(string $topic) => \trim($topic),
				\explode(",", $topics)
			);
		}

		foreach( $topics as $topic ){
			$result = $this->worker->addFunction(
				function_name: $topic,
				function: function(GearmanJob $job) use ($callback): void {
					$message = new Message(
						topic: $job->functionName(),
						payload: $job->workload(),
						reference: $job->unique(),
						attributes: [
							"handle" => $job->handle(),
						]
					);

					\call_user_func($callback, $message);
				},
				timeout: $options["timeout"] ?? 0
			);

			if( $result === false ){
				throw new SubscriptionException(
					\sprintf(
						"Failed to subscribe to %s topic.",
						$topic
					)
				);
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function loop(array $options = []): void
	{
		if( $this->worker === null ){
			throw new ConsumeException(
				"No GearmanWorker instance was given. ".
				"In order to process jobs, you must pass a GearmanWorker instance ".
				"into the constructor."
			);
		}

		$this->running = true;

		while( $this->worker->work() ){
			/**
			 * @psalm-suppress TypeDoesNotContainType
			 */
			if( $this->worker->returnCode() !== GEARMAN_SUCCESS ||
				$this->running === false ) {
				break;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function shutdown(): void
	{
		$this->running = false;
	}
}