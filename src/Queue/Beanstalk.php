<?php

namespace Syndicate\Queue;

use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Syndicate\Queue;
use Syndicate\Message;

/**
 * 
 * @property Pheanstalk $client
 * 
 */
class Beanstalk extends Queue
{
    /**
     * Beanstalkd adapter constructor.
     *
     * @param Pheanstalk $pheanstalk
     */
    public function __construct(string $name, Pheanstalk $pheanstalk)
    {
        $this->name = $name;
        $this->client = $pheanstalk;
    }

    /**
     * @inheritDoc
     */
    public function put($data, array $options = []): void
    {
        $this->client->putInTube(
            $this->name,
            $this->serialize($data),
            $options['priority'] ?? null,
            $options['delay'] ?? null,
            $options['ttr'] ??  null
        );
    }

    /**
     * @inheritDoc
     */
    public function get(array $options = []): ?Message
    {
        $job = $this->client->reserveFromTube(
            $this->name,
            $options['timeout'] ?? null
        );

        $payload = $this->deserialize(
            $job->getData()
        );

        return new Message($this, $job, $payload);
    }

    /**
     * @inheritDoc
     */
    public function many(int $max, array $options = []): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function release(Message $message, array $options = []): void
    {
        $this->client->release(
            $message->getSourceMessage(),
            $options['priority'] ?? null,
            $options['delay'] ?? null
        );
    }

    /**
     * @inheritDoc
     */
    public function delete(Message $message): void
    {
        $this->client->delete($message->getSourceMessage());
    }
}