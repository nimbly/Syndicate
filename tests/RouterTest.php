<?php

namespace Syndicate\Tests;

use PHPUnit\Framework\TestCase;
use Syndicate\Message;
use Syndicate\Queue\MockQueue;
use Syndicate\Router;

/**
 * @covers Syndicate\Router
 * @covers Syndicate\Message
 * @covers Syndicate\Queue\MockQueue
 * @covers Syndicate\MessageTransformer
 */
class RouterTest extends TestCase
{
    public function test_resolver()
    {
        $router = new Router(
            function(Message $message, $route): bool {

                return $message->getPayload()->name === $route;

            },
            [
                "FooEvent" => "Handlers\FooHandler@handleEvent"
            ]
        );

        $queue = new MockQueue("mockqueue", [
            \json_encode(["name" => "FooEvent"]),
            \json_encode(["name" => "BarEvent"]),
        ]);

        $message = $queue->get();

        $route = $router->resolve($message);

        $this->assertEquals("Handlers\FooHandler@handleEvent", $route);
    }
}