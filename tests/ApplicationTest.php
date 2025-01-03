<?php

use Carton\Container;
use Nimbly\Syndicate\Application;
use Nimbly\Syndicate\DeadletterPublisher;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\PublisherException;
use Nimbly\Syndicate\PubSub\Mock;
use Nimbly\Syndicate\Response;
use Nimbly\Syndicate\RouterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\Application
 */
class ApplicationTest extends TestCase
{
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

	public function test_no_response_acks_message(): void
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

	public function test_nack_response_releases_message(): void
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

	public function test_deadletter_response_publishes_message_to_deadletter(): void
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
						return Response::deadleter;
					};
				}
			},
			deadletter: new DeadletterPublisher($mock, "deadletter")
		);

		$application->listen("test_topic");

		$this->assertCount(0, $mock->getMessages("test_topic"));
		$this->assertCount(1, $mock->getMessages("deadletter"));
	}

	public function test_deadletter_response_with_no_deadletter_publisher_throws_exception(): void
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
						return Response::deadleter;
					};
				}
			}
		);

		$this->expectException(PublisherException::class);
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
}