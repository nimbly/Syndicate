<?php

namespace Syndicate\Tests;

use PHPUnit\Framework\TestCase;
use Syndicate\Queue\MockQueue;

/**
 * @covers Syndicate\Message
 * @covers Syndicate\MessageTransformer
 * @covers Syndicate\Queue\MockQueue
 */
class MessageTransfomerTest extends TestCase
{
    public function test_default_serializer()
    {
        $queue = new MockQueue("MockQueue");

        $sourceMessage = ["event" => "EventName"];

        $queue->put($sourceMessage);
        $message = $queue->get();

        $this->assertEquals(\json_encode($sourceMessage), $message->getSourceMessage());
    }

    public function test_custom_serializer()
    {
        $queue = new MockQueue("MockQueue");
        $queue->setSerializer("\serialize");

        $sourceMessage = ["event" => "EventName"];

        $queue->put($sourceMessage);
        $message = $queue->get();

        $this->assertEquals(\serialize($sourceMessage), $message->getSourceMessage());
    }

    public function test_default_deserializer()
    {
        $queue = new MockQueue("MockQueue");

        $sourceMessage = ["event" => "EventName"];

        $queue->put($sourceMessage);
        $message = $queue->get();

        $this->assertEquals(\json_decode(\json_encode($sourceMessage)), $message->getPayload());
    }

    public function test_custom_deserializer()
    {
        $queue = new MockQueue("MockQueue");
        $queue->setSerializer("\serialize");
        $queue->setDeserializer("\unserialize");

        $sourceMessage = ["event" => "EventName"];

        $queue->put($sourceMessage);
        $message = $queue->get();

        $this->assertEquals(\unserialize(\serialize($sourceMessage)), $message->getPayload());
    }
}