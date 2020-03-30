<?php

namespace Syndicate\Queue;

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Google\Cloud\PubSub\Topic;
use Syndicate\Message;
use Syndicate\Queue\Queue;

/**
 *
 * @property PubSubClient $client
 *
 */
class Google extends Queue
{
    /**
     * Google Cloud PubSub adapter.
     *
     * @param string $subscription
     * @param PubSubClient $client
     */
    public function __construct(string $subscription, PubSubClient $client)
    {
        $this->name = $subscription;
        $this->client = $client;
    }

    /**
     * Get the subscription.
     *
     * @return Subscription
     */
    protected function getSubscription(): Subscription
    {
        return $this->client->subscription($this->name);
    }

    /**
     * @inheritDoc
     */
    public function put($data, array $options = []): void
    {
        if( empty($options['topic']) ){
            throw new \Exception("Topic must be provided in options.");
        }

        $topic = $this->client->topic($options['topic']);

        $topic->publish([
            'data' => $this->serialize($data),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function get(array $options = []): ?Message
    {
        $messages = $this->many(1, $options);
        return $messages[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function many(int $max, array $options = []): array
    {
        $subscriptionMessages = $this->getSubscription()->pull([
            'maxMessages' => (int) $max
		]);

		return \array_map(
			function($message): Message {
				return new Message($this, $message, $this->deserialize($message->data()));
			},
			$subscriptionMessages ?: []
		);
    }

    /**
     * @inheritDoc
     */
    public function delete(Message $message): void
    {
        $this->getSubscription()->acknowledge($message->getSourceMessage());
    }

    /**
     * @inheritDoc
     */
    public function release(Message $message, array $options = []): void
    {
        return;
    }
}