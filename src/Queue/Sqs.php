<?php

namespace Syndicate\Queue;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Syndicate\Message;

/**
 * 
 * @property SqsClient $client
 * 
 */
class Sqs extends Queue
{
    /**
     * SQS adapter constructor
     *
     * @param string $name
     * @param SqsClient $sqsClient
     */
    public function __construct(string $name, SqsClient $sqsClient)
    {
        $this->name = $name;
        $this->client = $sqsClient;
    }

    /**
     * @inheritDoc
     */
    public function put($data, array $options = []): void
    {
        $message = [
            'QueueUrl' => $this->name,
            'MessageBody' => $this->serialize($data),
        ];

        if( array_key_exists('delay', $options) ){
            $message['DelaySeconds'] = $options['delay'];
        }

        if( array_key_exists('messageId', $options) ){
            $message['MessageDeduplicationId'] = $options['messageId'];
        }

        if( array_key_exists('groupId', $options) ){
            $message['MessageGroupId'] = $options['groupId'];
        }

        $this->client->sendMessage($message);
    }

    /**
     * @inheritDoc
     */
    public function get(array $options = []): ?Message
    {
        $sqsMessages = $this->many(1, $options);
        return $sqsMessages[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function many(int $max, array $options = []): array
    {
        $request = [
            'QueueUrl' => $this->name,
            'MaxNumberOfMessages' => (int) $max
        ];

        if( array_key_exists('timeout', $options) ){
            $request['WaitTimeSeconds'] = $options['timeout'];
        }

        /**
         * @var Result $response
         */
        $response = $this->client->receiveMessage($request);

        $sqsMessages = $response->get('Messages');
        $messages = [];

        if( $sqsMessages ){

            foreach( $sqsMessages as $sqsMessage ){

                // Deserialize message body
                $payload = $this->deserialize(
                    $sqsMessage['Body']
                );

                // Add message to set.
                $messages[] = new Message($this, $sqsMessage, $payload);
            }
        }

        return $messages;
    }

    /**
     * @inheritDoc
     */
    public function delete(Message $message): void
    {
        $request = [
            'QueueUrl' => $this->name,
            'ReceiptHandle' => $message->getSourceMessage()['ReceiptHandle'],
        ];

        $this->client->deleteMessage($request);
    }

    /**
     * @inheritDoc
     */
    public function release(Message $message, array $options = []): void
    {
        $request = [
            'QueueUrl' => $this->name,
            'ReceiptHandle' => $message->getSourceMessage()['ReceiptHandle'],
            'VisibilityTimeout' => (int) ($options['delay'] ?? 0),
        ];

        $this->client->changeMessageVisibility($request);
    }
}