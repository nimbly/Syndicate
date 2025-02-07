<?php

namespace Nimbly\Syndicate\Tests;

use Mockery;
use DateTime;
use ReflectionClass;
use Nimbly\Carton\Container;
use Psr\Log\LoggerInterface;
use Nimbly\Syndicate\Message;
use UnexpectedValueException;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Application;
use Nimbly\Syndicate\Router\Router;
use Nimbly\Syndicate\Adapter\Queue\Mock;
use Nimbly\Syndicate\Filter\RedirectFilter;
use Nimbly\Syndicate\Router\RouterInterface;
use Nimbly\Syndicate\Exception\RoutingException;
use Nimbly\Syndicate\Tests\Fixtures\TestHandler;
use Nimbly\Syndicate\Tests\Fixtures\TestMiddleware;
use Nimbly\Syndicate\Middleware\MiddlewareInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nimbly\Syndicate\Adapter\PubSub\Mock as PubSubMock;

/**
 * @covers Nimbly\Syndicate\Application
 */
class ApplicationTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_routing(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			$mock,
			new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return function(): Response {
						\posix_kill(\posix_getpid(), SIGINT);
						return Response::ack;
					};
				}
			}
		);

		$application->listen("test_topic");
		$this->assertCount(0, $mock->getMessages("test_topic"));
	}

	public function test_no_handler_throws_exception(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			$mock,
			new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return null;
				}
			}
		);

		$this->expectException(RoutingException::class);
		$application->listen("test_topic");
	}

	public function test_interrupt_signals(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			consumer: $mock,
			router: new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return function(): Response {
						\posix_kill(\posix_getpid(), SIGTERM);
						return Response::ack;
					};
				}
			},
			signals: [SIGTERM]
		);

		$application->listen("test_topic");
		$this->assertCount(0, $mock->getMessages("test_topic"));
	}

	public function test_empty_signals_logs_warning(): void
	{
		$logger = Mockery::mock(LoggerInterface::class);

		$logger->shouldReceive("warning");

		$application = new Application(
			consumer: new Mock,
			router: new Router([]),
			logger: $logger,
			signals: []
		);

		$logger->shouldHaveReceived("warning");
	}

	public function test_listen_with_loop_consumer(): void
	{
		$consumer = new PubSubMock(
			[
				"fruits" => [
					new Message("fruits", "apples"),
					new Message("fruits", "oranges"),
					new Message("fruits", "bananas"),
				]
			]
		);

		$application = new Application(
			consumer: $consumer,
			router: new Router([
				TestHandler::class
			])
		);

		$application->listen("fruits");

		$this->assertCount(0, $consumer->getMessages("fruits"));
	}

	public function test_listen_with_multiple_locations_on_consumer_interface_instance_throws_unexpected_value_exception(): void
	{
		$application = new Application(
			consumer: new Mock,
			router: new Router(handlers: [TestHandler::class])
		);

		$this->expectException(UnexpectedValueException::class);
		$application->listen(["test_topic", "foo"]);
	}

	public function test_listen_no_response_acks_message(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			$mock,
			new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return function(): void {
						\posix_kill(\posix_getpid(), SIGINT);
					};
				}
			}
		);

		$application->listen("test_topic");
		$this->assertCount(0, $mock->getMessages("test_topic"));
	}

	public function test_listen_nack_response_releases_message(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			$mock,
			new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return function(): Response {
						\posix_kill(\posix_getpid(), SIGINT);
						return Response::nack;
					};
				}
			}
		);

		$application->listen("test_topic");
		$this->assertCount(1, $mock->getMessages("test_topic"));
	}

	public function test_listen_deadletter_response_publishes_message_to_deadletter(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			consumer: $mock,
			router: new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return function(): Response {
						\posix_kill(\posix_getpid(), SIGINT);
						return Response::deadletter;
					};
				}
			},
			deadletter: new RedirectFilter($mock, "deadletter")
		);

		$application->listen("test_topic");

		$this->assertCount(0, $mock->getMessages("test_topic"));
		$this->assertCount(1, $mock->getMessages("deadletter"));
	}

	public function test_listen_deadletter_response_with_no_deadletter_publisher_throws_exception(): void
	{
		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			consumer: $mock,
			router: new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return function(): Response {
						\posix_kill(\posix_getpid(), SIGINT);
						return Response::deadletter;
					};
				}
			}
		);

		$this->expectException(RoutingException::class);
		$application->listen("test_topic");
	}

	public function test_dependency_injection(): void
	{
		$container = new Container;
		$container->set(DateTime::class, new DateTime("2025-01-01T00:00:01Z"));

		$mock = new Mock;
		$mock->publish(new Message("test_topic", "Ok"));

		$application = new Application(
			consumer: $mock,
			router: new class implements RouterInterface {
				public function resolve(Message $message): callable|string|null
				{
					return function(Message $message, DateTime $date): Response {
						\posix_kill(\posix_getpid(), SIGINT);

						if( empty($message) || empty($date) ){
							return Response::nack;
						}

						if( $date != new DateTime("2025-01-01T00:00:01Z") ){
							return Response::nack;
						}

						return Response::ack;
					};
				}
			},
			container: $container
		);

		$application->listen("test_topic");
		$this->assertCount(0, $mock->getMessages("test_topic"));
	}

	public function test_shutdown_with_loop_consumer(): void
	{
		$consumer = new PubSubMock(
			["fruits" => []]
		);

		$application = new Application(
			consumer: $consumer,
			router: new Router([
				TestHandler::class
			])
		);

		$application->listen("fruits");
		$application->shutdown();

		$this->assertFalse($consumer->getRunning());
	}

	public function test_compile_creates_callable_chain(): void
	{
		$application = new Application(new Mock, new Router([]));

		$reflectionClass = new ReflectionClass($application);
		$reflectionMethod = $reflectionClass->getMethod("compileMiddleware");
		$reflectionMethod->setAccessible(true);

		$chain = $reflectionMethod->invoke(
			$application,
			[
				new class implements MiddlewareInterface {
					public function handle(Message $message, callable $next): mixed
					{
						return $next(new Message("veggies", "broccoli"));
					}
				}
			],
			function(Message $message): string {
				return $message->getTopic();
			}
		);

		$this->assertIsCallable($chain);

		$this->assertEquals(
			"veggies",
			\call_user_func($chain, new Message("fruits", "apples"))
		);
	}

	public function test_normalize_invalid_middleware_throws_unexpected_value_exception(): void
	{
		$application = new Application(new Mock, new Router([]));

		$reflectionClass = new ReflectionClass($application);
		$reflectionMethod = $reflectionClass->getMethod("normalizeMiddleware");
		$reflectionMethod->setAccessible(true);

		$this->expectException(UnexpectedValueException::class);
		$reflectionMethod->invoke(
			$application,
			[
				\Nimbly\Syndicate\Adapter\PubSub\Mock::class
			]
		);
	}

	public function test_normalize_creates_class_string_instances(): void
	{
		$application = new Application(new Mock, new Router([]));

		$reflectionClass = new ReflectionClass($application);
		$reflectionMethod = $reflectionClass->getMethod("normalizeMiddleware");
		$reflectionMethod->setAccessible(true);

		$middleware = $reflectionMethod->invoke(
			$application,
			[
				TestMiddleware::class
			]
		);

		$this->assertCount(1, $middleware);
		$this->assertInstanceOf(TestMiddleware::class, $middleware[0]);
	}
}