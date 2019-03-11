<?php

namespace Syndicate\Tests;

use PHPUnit\Framework\TestCase;
use Syndicate\Queue\MockQueue;

class MessageTransfomerTest extends TestCase
{
    public function test_serialization()
    {
        $queue = new MockQueue("MockQueue");
        $queue->setSerializer("\json_encode");
        $queue->setDeserializer("\json_decode");

        $sourceMessage = ["event" => "EventName"];

        $queue->put($sourceMessage);

        $message = $queue->get();

        $this->assertEquals(\json_encode($sourceMessage), $message->getSourceMessage());
    }

    public function test_deserialization()
    {
        $queue = new MockQueue("MockQueue");
        $queue->setSerializer("\json_encode");
        $queue->setDeserializer("\json_decode");

        $sourceMessage = ["event" => "EventName"];

        $queue->put($sourceMessage);

        $message = $queue->get();

        $this->assertEquals(\json_decode(\json_encode($sourceMessage)), $message->getPayload());
    }
}