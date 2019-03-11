<?php

namespace Syndicate\PubSub;

use Syndicate\MessageTransformer;


abstract class PubSubAbstract extends MessageTransformer
{
    /**
     * PubSub client instance.
     *
     * @var mixed
     */
    protected $client;

    /**
     * Publish message to Subscription topic.
     *
     * @param string $topic
     * @param string $data
     * @param array $options
     * @return void
     */
    abstract public function publish(string $topic, string $data, array $options = []): void;

    /**
     * Listen to a specific topic and register a handler.
     *
     * @param string $topic
     * @param callable $handler
     * @param array $options
     * @return void
     */
    abstract public function listen(string $topic, callable $handler, array $options = []): void;

    /**
     * Subscribe to multiple topics with a distinct handler.
     *
     * @param array <string, callable>
     * @param callable $defaultHandler
     * @return void
     */
    abstract public function subscribe(array $subscriptions, callable $defaultHandler = null): void;
}