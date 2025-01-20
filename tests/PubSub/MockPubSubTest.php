<?php

use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\PubSub\Mock;
use Nimbly\Syndicate\PublisherException;

/**
 * @covers Nimbly\Syndicate\PubSub\Mock
 */
class MockPubSubTest extends TestCase
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

	public function test_publish_failure_throws_publisher_exception(): void
	{
		$mock = new Mock;

		$this->expectException(PublisherException::class);
		$mock->publish(new Message("test", "Ok"), ["exception" => true]);
	}

	public function test_subscribe(): void
	{
		$callback = function(Message $message): Response {
			return Response::ack;
		};

		$mock = new Mock(
			subscriptions: [
				"fruits" => $callback
			]
		);

		$subscription = $mock->getSubscription("fruits");

		$this->assertSame($callback, $subscription);
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
}