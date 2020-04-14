<?php

namespace Syndicate\Tests;

use Carton\Container;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Syndicate\Dispatcher;
use Syndicate\DispatchException;
use Syndicate\Message;
use Syndicate\Queue\MockQueue;
use Syndicate\Router;
use Syndicate\Tests\Fixtures\FooHandler;

/**
 * @covers Syndicate\Dispatcher
 * @covers Syndicate\Router
 * @covers Syndicate\Queue\MockQueue
 * @covers Syndicate\Message
 * @covers Syndicate\MessageTransformer
 * @covers Syndicate\DispatchException
 */
class DispatcherTest extends TestCase
{
    public function test_dispatch_with_closure_handler(): void
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

    public function test_dispatch_with_array_of_closure_handlers(): void
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

    public function test_default_handler(): void
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
        $defaultHandlerValue = null;
        $dispatcher->setDefaultHandler(function(Message $message) use (&$defaultHandlerValue){
            $defaultHandlerValue = 'ok';
        });

        $dispatcher->dispatch($queue->get());

        $this->assertEquals("ok", $defaultHandlerValue);
    }

    public function test_no_default_handler_throws(): void
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

        $this->expectException(DispatchException::class);
        $dispatcher->dispatch($queue->get());
	}

	public function test_set_container(): void
	{
		$router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
            },
            [
            ]
		);

		$dispatcher = new Dispatcher($router);

		$container = new Container;

		$dispatcher->setContainer($container);

		$reflectionClass = new ReflectionClass($dispatcher);
		$reflectionProperty = $reflectionClass->getProperty('container');
		$reflectionProperty->setAccessible(true);

		$this->assertSame(
			$container,
			$reflectionProperty->getValue($dispatcher)
		);
	}

	public function test_get_callable_handlers_string_class_not_found_throws_exception(): void
	{
		$router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
            }
		);

		$dispatcher = new Dispatcher($router);

		$reflectionClass = new ReflectionClass($dispatcher);
		$reflectionMethod = $reflectionClass->getMethod("getCallableHandlers");
		$reflectionMethod->setAccessible(true);

		$this->expectException(DispatchException::class);

		$reflectionMethod->invokeArgs(
			$dispatcher,
			["App\Handlers\FooHandler@onFooCreated"]
		);
	}

	public function test_get_callable_handlers_not_callable_throws_exception(): void
	{
		$router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
            }
		);

		$dispatcher = new Dispatcher($router);

		$reflectionClass = new ReflectionClass($dispatcher);
		$reflectionMethod = $reflectionClass->getMethod("getCallableHandlers");
		$reflectionMethod->setAccessible(true);

		$this->expectException(DispatchException::class);

		$reflectionMethod->invokeArgs(
			$dispatcher,
			[new \stdClass]
		);
	}

	public function test_get_parameters_for_array_callable(): void
	{
		$router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
            }
		);

		$dispatcher = new Dispatcher($router);

		$reflectionClass = new ReflectionClass($dispatcher);
		$reflectionMethod = $reflectionClass->getMethod("getParametersForCallable");
		$reflectionMethod->setAccessible(true);

		/**
		 * @var array<ReflectionParameter> $reflectionParameters
		 */
		$reflectionParameters = $reflectionMethod->invokeArgs(
			$dispatcher,
			[
				[new FooHandler(new \DateTime), "onFooCreated"]
			]
		);

		$this->assertCount(1, $reflectionParameters);
		$this->assertEquals(
			"message",
			$reflectionParameters[0]->getName()
		);

		$this->assertEquals(
			"Syndicate\\Message",
			$reflectionParameters[0]->getType()->getName()
		);
	}

	public function test_get_parameters_for_function(): void
	{
		$router = new Router(
            function(Message $message, $route): bool
            {
                return $message->getPayload()->name === $route;
            }
		);

		$dispatcher = new Dispatcher($router);

		$reflectionClass = new ReflectionClass($dispatcher);
		$reflectionMethod = $reflectionClass->getMethod("getParametersForCallable");
		$reflectionMethod->setAccessible(true);

		$callable = function(Message $message): void {
			$message->delete();
		};

		/**
		 * @var array<ReflectionParameter> $reflectionParameters
		 */
		$reflectionParameters = $reflectionMethod->invokeArgs(
			$dispatcher,
			[
				$callable
			]
		);

		$this->assertCount(1, $reflectionParameters);
		$this->assertEquals(
			"message",
			$reflectionParameters[0]->getName()
		);

		$this->assertEquals(
			"Syndicate\\Message",
			$reflectionParameters[0]->getType()->getName()
		);
	}
}