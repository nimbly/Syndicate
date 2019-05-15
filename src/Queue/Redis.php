<?php

namespace Syndicate\Queue;

use Predis\Client;
use Syndicate\Message;


/**
 * 
 * @property Client $client
 * 
 */
class Redis extends Queue
{
    /**
     * Time to wait in seconds before checking Redis again.
     * 
     * This timeout is necessary because without a breather, the application
     * will consume all CPU resources.
     * 
     * Defaults to 0.5 seconds.
     *
     * @var float
     */
    protected $backoff;

    /**
     * Redis adapter constructor
     *
     * @param string $name
     * @param Client $redis
     * @param float $backoff
     */
    public function __construct(string $name, Client $redis, float $backoff = 0.5)
    {
        $this->name = $name;
        $this->client = $redis;
        $this->backoff = $backoff;
    }

    /**
     * @inheritDoc
     */
    public function put($data, array $options = []): void
    {
        $this->client->rpush($this->name, $this->serialize($data));
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
        $messages = [];

        for( $i = 0; $i < $max; $i++ ){
            
            if( ($message = $this->client->lpop($this->name)) ){
                $messages[] =  new Message($this, $message, $this->deserialize($message));
            }
        }

        if( empty($messages) ){
            \usleep((int) ($this->backoff * 1000000));
        }

        return $messages;
    }

    /**
     * @inheritDoc
     */
    public function delete(Message $message): void
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function release(Message $message, array $options = []): void
    {
        $this->put($message->getPayload());
    }
}