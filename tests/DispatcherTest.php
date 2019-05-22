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
    public function test_dispatch_with_closure()
    {
        $queue = new MockQueue("mockqueue", [
            \json_encode(["name" => "FooEvent"])
        ]);

        $value = null;

        $router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
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

    public function test_dispatch_with_array_closures()
    {
        $queue = new MockQueue("mockqueue", [
            \json_encode(["name" => "FooEvent"])
        ]);

        $closure1 = null;
        $closure2 = null;

        $router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
            },
            [
                "FooEvent" => [
                    function(Message $message) use (&$closure1): void {
                        $closure1 = "ok";
                    },

                    function(Message $message) use (&$closure2): void {
                        $closure2 = "ok";
                    },
                ]
            ]
        );

        $dispatcher = new Dispatcher($router);
        $dispatcher->dispatch($queue->get());

        $this->assertEquals("ok", $closure1);
        $this->assertEquals("ok", $closure2);
    }

    public function test_default_handler()
    {
        $queue = new MockQueue("mockqueue", [
            \json_encode(["name" => "FooEvent"])
        ]);

        $router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
            },
            [
            ]
        );

        $dispatcher = new Dispatcher($router);
        $dispatcher->dispatch($queue->get());
    }
}