<?php

namespace Nimbly\Syndicate\PubSub;

use Throwable;
use Nimbly\Resolve\Resolve;
use Nimbly\Syndicate\Message;
use UnexpectedValueException;
use PhpMqtt\Client\MqttClient;
use Psr\Container\ContainerInterface;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\ConnectionException;
use Nimbly\Syndicate\LoopConsumerInterface;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;

class Mqtt implements PublisherInterface, LoopConsumerInterface
{
	use Resolve;

	/**
	 * @param MqttClient $client
	 */
	public function __construct(
		protected MqttClient $client,
		protected ?ContainerInterface $container = null,
		array $signals = [SIGINT, SIGHUP, SIGTERM]
	)
	{
		if( \extension_loaded("pcntl") ){
			\pcntl_async_signals(true);

			foreach( $signals as $signal ){
				$result = \pcntl_signal(
					$signal,
					[$this, "shutdown"]
				);

				if( $result === false ){
					throw new UnexpectedValueException("Could not attach signal (" . $signal . ") handler.");
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 * @return null
	 *
	 * Options:
	 * 	* `qos` integer, One of `MqttClient::QOS_AT_MOST_ONCE`, `MqttClient::QOS_AT_LEAST_ONCE`, or `MqttClient::QOS_EXACTLY_ONCE`. Defaults to `MqttClient::QOS_AT_MOST_ONCE`.
	 *  * `retain` boolean, Whether to retain the message on the source. Defaults to false.
	 */
	public function publish(Message $message, array $options = []): ?string
	{
		$this->connect();

		try {

			$this->client->publish(
				topic: $message->getTopic(),
				message: $message->getPayload(),
				qualityOfService: (int) ($options["qos"] ?? MqttClient::QOS_AT_MOST_ONCE),
				retain: (bool) ($options["retain"] ?? false)
			);
		}
		catch( ConnectingToBrokerFailedException $exception ){
			throw new ConnectionException(
				message: "Connection to MQTT broker failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ) {
			throw new PublisherException(
				message: "Failed to publish message.",
				previous: $exception
			);
		}

		return null;
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `qos` One of `MqttClient::QOS_AT_MOST_ONCE`, `MqttClient::QOS_AT_LEAST_ONCE`, or `MqttClient::QOS_EXACTLY_ONCE`. Defaults to `MqttClient::QOS_AT_MOST_ONCE`.
	 */
	public function subscribe(string|array $topic, string|callable $callback, array $options = []): void
	{
		if( !\is_array($topic) ){
			$topic = [$topic];
		}

		$this->connect();

		foreach( $topic as $t ){
			try {

				$this->client->subscribe(
					topicFilter: $t,
					callback: function(string $topic, string $body, bool $retained, array $matched) use ($callback): void {
						$message = new Message(
							topic: $topic,
							payload: $body,
							attributes: [
								"retained" => $retained,
								"matched" => $matched,
							]
						);

						$this->call(
							$this->makeCallable($callback),
							$this->container,
							[Message::class => $message]
						);
					},
					qualityOfService: $options["qos"] ?? MqttClient::QOS_AT_MOST_ONCE
				);
			}
			catch( ConnectingToBrokerFailedException $exception ){
				throw new ConnectionException(
					message: "Connection to MQTT broker failed.",
					previous: $exception
				);
			}
			catch( Throwable $exception ){
				throw new ConsumerException(
					message: "Failed to subscribe to topic.",
					previous: $exception
				);
			}
		}
	}

	/**
	 * @inheritDoc
	 *
	 * Options:
	 * 	* `allow_sleep` (boolean) Defaults to true.
	 *  * `exit_when_empty` (boolea) Defaults to false.
	 *  * `timeout` (integer|null) Defaults to null.
	 */
	public function loop(array $options = []): void
	{
		$this->connect();

		try {

			$this->client->loop(
				allowSleep: (bool) ($options["allow_sleep"] ?? true),
				exitWhenQueuesEmpty: (bool) ($options["exit_when_empty"] ?? false),
				queueWaitLimit: $options["timeout"] ?? null,
			);
		}
		catch( ConnectingToBrokerFailedException $exception ){
			throw new ConnectionException(
				message: "Connection to MQTT broker failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function shutdown(): void
	{
		try {

			$this->client->interrupt();
		}
		catch( ConnectingToBrokerFailedException $exception ){
			throw new ConnectionException(
				message: "Connection to MQTT broker failed.",
				previous: $exception
			);
		}
		catch( Throwable $exception ){
			throw new ConsumerException(
				message: "Failed to shutdown consumer.",
				previous: $exception
			);
		}
	}

	/**
	 * Connect to Mqtt.
	 *
	 * @return void
	 */
	private function connect(): void
	{
		if( !$this->client->isConnected() ){

			try {

				$this->client->connect();
			}
			catch( Throwable $exception ){
				throw new ConnectionException(
					message: "Connection to MQTT broker failed.",
					previous: $exception
				);
			}
		}
	}

	/**
	 * Disconnect when tearing down.
	 */
	public function __destruct()
	{
		if( $this->client->isConnected() ){
			$this->client->disconnect();
		}
	}
}