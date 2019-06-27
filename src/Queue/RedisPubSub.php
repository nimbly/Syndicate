<?php

namespace Syndicate\Queue;

use Predis\Client;
use Predis\PubSub\Consumer;
use Predis\PubSub\DispatcherLoop;
use Syndicate\Message;

/**
 * 
 * @property Client $client
 * 
 */
class RedisPubSub extends Queue
{
    /**
     * RedisPubSub adapter constructor
     *
     * @param string $topic
     * @param Client $redis
     */
    public function __construct(string $topic, Client $redis)
    {
        $this->name = $topic;
        $this->client = $redis;
    }

    /**
     * @inheritDoc
     */
    public function put($data, array $options = []): void
    {
        $this->client->publish($this->name, $this->serialize($data));
    }

    /**
     * Use the listen() method to pull data off this queue.
     *
     * @param array $options
     * @return Message|null
     */
    public function get(array $options = []): ?Message
    {
        return null;
    }

    /**
     * Use the listen() method to pull data off this queue.
     *
     * @param integer $max
     * @param array $options
     * @return array<Message>
     */
    public function many(int $max, array $options = []): array
    {
        return [];
    }

    /**
     * Delete a message off the queue
     *
     * @param Message $message
     * @return void
     */
    public function delete(Message $message): void
    {
        return;
    }

    /**
     * Release a message back on to the queue
     *
     * @param Message $message
     * @param array $options
     * @return void
     */
    public function release(Message $message, array $options = []): void
    {
        $this->put($message->getPayload(), $options);
    }

    /**
     *@inheritDoc
     */
    public function listen(callable $callback, int $pollingTimeout = 20): void
    {
        $self = $this;

        $this->client->pubSubLoop(['subscribe' => $this->name], function(Consumer $consumer, object $message) use ($callback, $self): void {

            if( $message->kind === "message" ){
                
                $callback(
                    new Message($self, $message, $self->deserialize($message->payload))
                );
            }

        });
    }
}