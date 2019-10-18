<?php

namespace Syndicate\Queue;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Syndicate\Message;
use Syndicate\Queue\Queue;

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
     * @param string $name
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
            $options['priority'] ?? PheanstalkInterface::DEFAULT_PRIORITY,
            $options['delay'] ?? PheanstalkInterface::DEFAULT_DELAY,
            $options['ttr'] ??  PheanstalkInterface::DEFAULT_TTR
        );
    }

    /**
     * @inheritDoc
     */
    public function get(array $options = []): ?Message
    {
        $message = $this->many(1, $options);

        if( empty($message) ){
            return null;
        }

        return $message[0];
    }

    /**
     * @inheritDoc
     */
    public function many(int $max, array $options = []): array
    {
        $job = $this->client->reserveFromTube(
            $this->name,
            $options['timeout'] ?? null
        );

		/** @psalm-suppress DocblockTypeContradiction */
        if( empty($job) ){
            return [];
        }

        $payload = $this->deserialize(
            $job->getData()
        );

        return [
            new Message($this, $job, $payload)
        ];
    }

    /**
     * @inheritDoc
     */
    public function release(Message $message, array $options = []): void
    {
        $this->client->release(
            $message->getSourceMessage(),
            $options['priority'] ?? PheanstalkInterface::DEFAULT_PRIORITY,
            $options['delay'] ?? PheanstalkInterface::DEFAULT_DELAY
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