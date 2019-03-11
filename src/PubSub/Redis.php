<?php

namespace Syndicate\PubSub;

use Predis\Client;
use Predis\PubSub\Consumer;
use Predis\PubSub\DispatcherLoop;
use Syndicate\Message;

class Redis extends PubSubAbstract
{
    /**
     * Redis adapter constructor
     *
     * @param Client $redis
     */
    public function __construct(Client $redis)
    {
        $this->client = $redis;
    }

    /**
     * @inheritDoc
     */
    public function publish(string $topic, string $data, array $options = []): void
    {
        $this->client->publish($topic, $this->serialize($data));
    }

    /**
     *@inheritDoc
     */
    public function listen(string $topic, callable $handler, array $options = []): void
    {
        $self = $this;

        $this->client->pubSubLoop(['subscribe' => $topic], function(Consumer $consumer, object $message) use ($handler, $self): void {

            if( $message->kind === "message" ){
                
                $handler(
                    new Message($message, $self->deserialize($message->payload))
                );
            }

        });
    }

    /**
     * @inheritDoc
     */
    public function subscribe(array $subscriptions, callable $defaultHandler = null): void
    {
        $consumer = $this->client->pubSubLoop();
        $dispatcherLoop = new DispatcherLoop($consumer);

        $self = $this;

        foreach( $subscriptions as $topic => $handler ){

            $dispatcherLoop->attachCallback($topic, function(string $message) use ($self, $handler): void {

                $handler(
                    new Message($message, $self->deserialize($message))
                );

            });
        }

        if( $defaultHandler ){
            $dispatcherLoop->defaultCallback(function(object $message) use ($self, $defaultHandler): void {

                $defaultHandler(
                    new Message($message, $self->deserialize($message->payload))
                );
                
            });
        }
        
        $dispatcherLoop->run();
    }
}