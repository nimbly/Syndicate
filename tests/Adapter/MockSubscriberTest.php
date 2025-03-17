<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\MockSubscriber;
use Nimbly\Syndicate\Exception\PublishException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass(MockSubscriber::class)]
class MockSubscriberTest extends TestCase
{
	public function test_publish(): void
	{
		$message = new Message("test", "Ok");

		$mock = new MockSubscriber;
		$mock->publish($message);

		$messages = $mock->getMessages("test");

		$this->assertCount(1, $messages);
		$this->assertSame($message, $messages[0]);
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = new MockSubscriber;

		$this->expectException(PublishException::class);
		$mock->publish(new Message("test", "Ok"), ["exception" => true]);
	}

	public function test_subscribe(): void
	{
		$callback = function(Message $message): Response {
			return Response::ack;
		};

		$mock = new MockSubscriber;
		$mock->subscribe("fruits", $callback);

		$subscription = $mock->getSubscription("fruits");

		$this->assertSame($callback, $subscription);
	}

	public function test_subscribe_with_array_of_topics(): void
	{
		$callback = function(Message $message): Response {
			return Response::ack;
		};

		$mock = new MockSubscriber;
		$mock->subscribe(["fruits", "veggies"], $callback);

		$subscription = $mock->getSubscription("fruits");
		$this->assertSame($callback, $subscription);

		$subscription = $mock->getSubscription("veggies");
		$this->assertSame($callback, $subscription);
	}

	public function test_subscribe_comma_separated_list_of_topics(): void
	{
		$callback = function(Message $message): Response {
			return Response::ack;
		};

		$mock = new MockSubscriber;
		$mock->subscribe("fruits, veggies", $callback);

		$subscription = $mock->getSubscription("fruits");
		$this->assertSame($callback, $subscription);

		$subscription = $mock->getSubscription("veggies");
		$this->assertSame($callback, $subscription);
	}

	public function test_loop_topic_with_no_messages_skips(): void
	{
		$callback = function(Message $message): Response {
			return Response::ack;
		};

		$mock = new MockSubscriber(messages: ["veggies" => [new Message("veggies", "OK")]]);
		$mock->subscribe("fruits, veggies", $callback);

		$mock->loop();

		$this->assertCount(0, $mock->getMessages("veggies"));
	}

	public function test_loop(): void
	{
		$mock = new MockSubscriber(
			messages: [
				"fruits" => [
					new Message("fruits", "apples"),
					new Message("fruits", "oranges"),
					new Message("fruits", "pears"),
				],

				"veggies" => [
					new Message("veggies", "broccoli"),
				]
			],
			subscriptions: [
				"fruits" => function(Message $message): Response {
					return Response::ack;
				}
			]
		);

		$mock->loop();

		$this->assertCount(0, $mock->getMessages("fruits"));
		$this->assertCount(1, $mock->getMessages("veggies"));
	}

	public function test_loop_exits_when_shutdown(): void
	{
		$mock = new MockSubscriber(
			messages: [
				"fruits" => [
					new Message("fruits", "apples"),
					new Message("fruits", "oranges"),
					new Message("fruits", "pears"),
				]
			]
		);

		$mock->subscribe(
			"fruits",
			function() use ($mock): Response {
				$mock->shutdown();
				return Response::ack;
			}
		);

		$mock->loop();

		$this->assertCount(2, $mock->getMessages("fruits"));
	}

	public function test_shutdown(): void
	{
		$mock = new MockSubscriber;
		$this->assertFalse($mock->getRunning());

		$reflectionClass = new ReflectionClass($mock);
		$reflectionProperty = $reflectionClass->getProperty("running");

		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($mock, true);

		$this->assertTrue($mock->getRunning());

		$mock->shutdown();

		$this->assertFalse($mock->getRunning());
	}

	public function test_flush_all_messages(): void
	{
		$mock = new MockSubscriber;
		$mock->publish(new Message("fruits", "ok"));
		$mock->publish(new Message("fruits", "ok"));

		$this->assertCount(2, $mock->getMessages("fruits"));

		$mock->flushMessages();

		$this->assertCount(0, $mock->getMessages("fruits"));
	}

	public function test_flush_topic_messages(): void
	{
		$mock = new MockSubscriber;
		$mock->publish(new Message("fruits", "ok"));
		$mock->publish(new Message("fruits", "ok"));
		$mock->publish(new Message("veggies", "ok"));
		$mock->publish(new Message("veggies", "ok"));

		$this->assertCount(2, $mock->getMessages("fruits"));
		$this->assertCount(2, $mock->getMessages("veggies"));

		$mock->flushMessages("fruits");

		$this->assertCount(0, $mock->getMessages("fruits"));
		$this->assertCount(2, $mock->getMessages("veggies"));
	}
}