<?php

namespace Nimbly\Syndicate\Tests\Adapter\PubSub;

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\PubSub\Mock;
use Nimbly\Syndicate\Exception\PublishException;

/**
 * @covers Nimbly\Syndicate\Adapter\PubSub\Mock
 */
class MockTest extends TestCase
{
	public function test_publish(): void
	{
		$message = new Message("test", "Ok");

		$mock = new Mock;
		$mock->publish($message);

		$messages = $mock->getMessages("test");

		$this->assertCount(1, $messages);
		$this->assertSame($message, $messages[0]);
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = new Mock;

		$this->expectException(PublishException::class);
		$mock->publish(new Message("test", "Ok"), ["exception" => true]);
	}

	public function test_subscribe(): void
	{
		$callback = function(Message $message): Response {
			return Response::ack;
		};

		$mock = new Mock;
		$mock->subscribe("fruits", $callback);

		$subscription = $mock->getSubscription("fruits");

		$this->assertSame($callback, $subscription);
	}

	public function test_subscribe_with_array_of_topics(): void
	{
		$callback = function(Message $message): Response {
			return Response::ack;
		};

		$mock = new Mock;
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

		$mock = new Mock;
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

		$mock = new Mock(messages: ["veggies" => [new Message("veggies", "OK")]]);
		$mock->subscribe("fruits, veggies", $callback);

		$mock->loop();

		$this->assertCount(0, $mock->getMessages("veggies"));
	}

	public function test_loop(): void
	{
		$mock = new Mock(
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
		$mock = new Mock(
			messages: [
				"fruits" => [
					new Message("fruits", "apples"),
					new Message("fruits", "oranges"),
					new Message("fruits", "pears"),
				]
			],
			subscriptions: [
				"fruits" => function(Message $message): Response {
					return Response::ack;
				}
			]
		);

		$mock->shutdown();
		$mock->loop();

		$this->assertCount(2, $mock->getMessages("fruits"));
	}

	public function test_shutdown(): void
	{
		$mock = new Mock;
		$mock->shutdown();

		$this->assertTrue($mock->getIsShutdown());
	}
}