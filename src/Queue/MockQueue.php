<?php

namespace Syndicate\Queue;

use Syndicate\Message;

class MockQueue extends Queue
{
    /**
     * @inheritDoc
     */
    protected $name;

    /**
     * Source messages.
     *
     * @var array<mixed>
     */
    protected $messages;

    /**
     * MockQueue constructor
     *
     * @param string $name
     * @param array<mixed> $messages
     */
    public function __construct(string $name, array $messages = [])
    {
        $this->name = $name;
        $this->messages = $messages;
    }

    /**
     * @inheritDoc
     */
    public function put($data, array $options = []): void
    {
        $this->messages[] = $this->serialize($data);
    }

    /**
     * @inheritDoc
     */
    public function get(array $options = []): ?Message
    {
        $message = array_shift($this->messages);

        return new Message($this, $message,
            $this->deserialize($message)
        );
    }

    /**
     * @inheritDoc
     */
    public function many(int $max, array $options = []): array
    {
        $messages = array_slice($this->messages, 0, $max);

        foreach( $messages as &$message ){
            $message = new Message($this, $message, $this->deserialize($message));
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
        $this->put($message);
    }
}