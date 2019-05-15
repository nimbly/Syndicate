<?php

namespace Shuttle\Tests;

use PHPUnit\Framework\TestCase;
use Syndicate\Dispatcher;
use Syndicate\Message;
use Syndicate\Queue\MockQueue;
use Syndicate\Router;

/**
 * @covers Syndicate\Dispatcher
 * @covers Syndicate\Router
 * @covers Syndicate\Queue\MockQueue
 * @covers Syndicate\Message
 * @covers Syndicate\MessageTransformer
 */
class DispatcherTest extends TestCase
{
    public function test_dispatch()
    {
        $queue = new MockQueue("mockqueue", [
            \json_encode(["name" => "FooEvent"])
        ]);

        $value = null;

        $router = new Router(
            function(Message $message, $route): bool
            {
                return $message->name === $route;
            },
            [
                "FooEvent" => function(Message $message) use (&$value): void {
                    $value = "ok";
                }
            ]
        );

        $dispatcher = new Dispatcher($router);
        $dispatcher->dispatch($queue->get());

        $this->assertEquals("ok", $value);
    }
}