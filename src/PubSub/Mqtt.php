<?php

namespace Nimbly\Syndicate\PubSub;

use Throwable;
use Nimbly\Syndicate\Message;
use PhpMqtt\Client\MqttClient;
use Nimbly\Syndicate\ConsumerException;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PublisherInterface;
use Nimbly\Syndicate\ConnectionException;
use Nimbly\Syndicate\LoopConsumerInterface;

class Mqtt implements PublisherInterface, LoopConsumerInterface
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
				qualityOfService: $options["qos"] ?? 0,
				retain: $options["retain"] ?? false
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
	public function subscribe(string|array $topic, callable $callback, array $options = []): void
	{
		if( !\is_array($topic) ){
			$topic = [$topic];
		}

		foreach( $topic as $t ){
			try {

				$this->client->subscribe(
					topicFilter: $t,
					callback: $callback,
					qualityOfService: $options["qos"] ?? MqttClient::QOS_AT_MOST_ONCE
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
				allowSleep: $options["allow_sleep"] ?? true,
				exitWhenQueuesEmpty: $options["exit_when_empty"] ?? false,
				queueWaitLimit: $options["timeout"] ?? null,
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
					message: "Failed to connect.",
					previous: $exception
				);
			}
		}
	}
}