<?php

namespace Syndicate\PubSub;

use Aws\Sns\SnsClient;


class Sns extends PubSubAbstract
{
    /**
     * Sns constructor.
     *
     * @param SnsClient $snsClient
     */
    public function __construct(SnsClient $snsClient)
    {
        $this->client = $snsClient;
    }

    /**
     * @inheritDoc
     */
    public function publish(string $topic, string $data, array $options = []): void
    {
        $this->client->publish([
            "TopicArn" => $topic,
            "Message" => $this->serialize($data)
        ]);
    }

    /**
     * @inheritDoc
     */
    public function listen(string $topic, callable $handler, array $options = []): void
    {

    }

    /**
     * @inheritDoc
     */
    public function subscribe(array $subscriptions, callable $defaultHandler = null): void
    {
        
    }
}