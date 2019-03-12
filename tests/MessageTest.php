<?php

namespace Syndicate\Tests;

use PHPUnit\Framework\TestCase;
use Syndicate\Message;
use Syndicate\Queue\MockQueue;

/**
 * @covers \Syndicate\Message
 * @covers \Syndicate\Queue\MockQueue
 */
class MessageTest extends TestCase
{
    public function test_get_source_message()
    {
        $sourceMessage = [
            "id" => "c9dfe490-12d9-43ea-a85d-2beaa8520d0d",
            "body" => '{"event": "SomeEvent", "data": {"name": "Joe Example", "email": "joe@example.com"}}'
        ];

        $message = new Message(new MockQueue('TestQueue'), $sourceMessage, json_decode($sourceMessage["body"]));

        $this->assertEquals($sourceMessage, $message->getSourceMessage());
    }

    public function test_get_payload()
    {
        $sourceMessage = [
            "id" => "c9dfe490-12d9-43ea-a85d-2beaa8520d0d",
            "body" => '{"event": "SomeEvent", "data": {"name": "Joe Example", "email": "joe@example.com"}}'
        ];

        $message = new Message(new MockQueue('TestQueue'), $sourceMessage, json_decode($sourceMessage["body"]));

        $this->assertEquals(json_decode($sourceMessage['body']), $message->getPayload());
    }
}