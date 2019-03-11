<?php

namespace Syndicate\Queue;

use Psr\Log\LoggerInterface;
use Syndicate\Message;
use Syndicate\MessageTransformer;


abstract class Queue extends MessageTransformer
{
    /**
     * Queue name.
     *
     * @var string
     */
    protected $name;

    /**
     * Client driver instance
     *
     * @var mixed
     */
    protected $client;

    /**
     * Put data on the queue
     *
     * @param mixed $data
     * @param array $options
     * @return void
     */
    abstract public function put($data, array $options = []): void;

    /**
     * Get a single message off the queue.
     *
     * @param array $options
     * @return Message|null
     */
    abstract public function get(array $options = []): ?Message;

    /**
     * Get many messages off the queue.
     *
     * @param integer $max
     * @param array $options
     * @return array<Message>
     */
    abstract public function many(int $max, array $options = []): array;

    /**
     * Delete a message off the queue
     *
     * @param Message $message
     * @return void
     */
    abstract public function delete(Message $message): void;

    /**
     * Release a message back on to the queue
     *
     * @param Message $message
     * @param array $options
     * @return void
     */
    abstract public function release(Message $message, array $options = []): void;

    /**
     * Block listen for new messages on queue. Executes callback on new Message arrival.
     *
     * @param callable $callback
     * @param int $pollingTimeout
     * @return void
     */
    public function listen(callable $callback, int $pollingTimeout = 20): void
    {
        do
        {
            $message = $this->get(['timeout' => $pollingTimeout]);

            if( $message ){

                $callback($message);
            }
        }
        while(true);
    }

    /**
     * Get the queue client
     *
     * @return mixed
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get the queue name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Call a method on the client itself.
     *
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function __call(string $method, array $params)
    {
        return call_user_func_array([$this->client, $method], $params);
    }
}