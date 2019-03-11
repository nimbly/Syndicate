<?php

namespace Syndicate;

use Syndicate\Queue\Queue;

class Message
{
    /**
     * A refererence to the Queue that generated this message.
     *
     * @var Queue
     */
    protected $queue;

    /**
     * Source message object/data.
     *
     * @var mixed
     */
    protected $sourceMessage;

    /**
     * The payload of the message.
     *
     * @var mixed
     */
    protected $payload;

    /**
     * Message constructor.
     *
     * @param Queue $queue
     * @param mixed $sourceMessage
     * @param mixed $payload
     */
    public function __construct(Queue $queue, $sourceMessage, $payload)
    {
        $this->queue = $queue;
        $this->sourceMessage = $sourceMessage;
        $this->payload = $payload;
    }

    /**
     * Get the raw message instance.
     *
     * @return mixed
     */
    public function getSourceMessage()
    {
        return $this->sourceMessage;
    }

    /**
     * Get the payload from the 
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Acknolwedge the message.
     *
     * @return void
     */
    public function delete(): void
    {
        $this->queue->delete($this);
    }

    /**
     * Release the message back onto the queue.
     *
     * @return void
     */
    public function release(): void
    {
        $this->queue->release($this);
    }
}