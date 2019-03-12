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
     * Redis adapter constructor
     *
     * @param string $name
     * @param Client $redis
     */
    public function __construct(string $name, Client $redis)
    {
        $this->name = $name;
        $this->client = $redis;
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
        if( ($data = $this->client->lpop($this->name)) ){

            $payload = $this->deserialize($data);
            return new Message($this, $data, $payload);
        }

        return null;
    }
    
    /**
     * @inheritDoc
     */
    public function many(int $max, array $options = []): array
    {
        $messages = [];

        for( $i = 0; $i < $max; $i++ ){
            
            if( ($data = $this->client->lpop($this->name)) ){
                
                $payload = $this->deserialize($data);
                $messages[] =  new Message($this, $data, $payload);
            }
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