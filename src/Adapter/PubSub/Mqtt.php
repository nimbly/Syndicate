<?php

namespace Nimbly\Syndicate\Adapter\PubSub;

use Throwable;
use Nimbly\Syndicate\Message;
use PhpMqtt\Client\MqttClient;
use Nimbly\Syndicate\Adapter\PublisherInterface;
use Nimbly\Syndicate\Adapter\SubscriberInterface;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Nimbly\Syndicate\Exception\SubscriptionException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;

class Mqtt implements PublisherInterface, SubscriberInterface
{
	/**
	 * @param MqttClient $client
	 */
	public function __construct(
		protected MqttClient $client
	)
	{
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
			throw new PublishException(
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
	public function subscribe(string|array $topics, callable $callback, array $options = []): void
	{
		if( !\is_array($topics) ){
			$topics = \array_map(
				fn(string $topic) => \trim($topic),
				\explode(",", $topics)
			);
		}

		$this->connect();

		foreach( $topics as $topic ){
			try {

				$this->client->subscribe(
					topicFilter: $topic,
					callback: function(string $topic, string $body, bool $retained, array $matched) use ($callback): void {
						$message = new Message(
							topic: $topic,
							payload: $body,
							attributes: [
								"retained" => $retained,
								"matched" => $matched,
							]
						);

						\call_user_func($callback, $message);
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
				throw new SubscriptionException(
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
			throw new ConsumeException(
				message: "Failed to consume message.",
				previous: $exception
			);
		}

		$this->disconnect();
	}

	/**
	 * @inheritDoc
	 */
	public function shutdown(): void
	{
		try {

			$this->client->interrupt();
		}
		catch( Throwable $exception ){
			throw new ConnectionException(
				message: "Connection to MQTT broker failed.",
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
	 * Disconnect from Mqtt.
	 *
	 * @return void
	 */
	private function disconnect(): void
	{
		if( $this->client->isConnected() ){

			try {

				$this->client->disconnect();
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
		$this->disconnect();
	}
}