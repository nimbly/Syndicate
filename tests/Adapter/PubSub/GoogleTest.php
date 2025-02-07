<?php

namespace Nimbly\Syndicate\Tests\Adapter\PubSub;

use Mockery;
use Exception;
use Nimbly\Syndicate\Message;
use Google\Cloud\PubSub\Topic;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\PubSub\Google;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Google\Cloud\PubSub\Message as PubSubMessage;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @covers Nimbly\Syndicate\Adapter\PubSub\Google
 */
class GoogleTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockTopic = Mockery::mock(Topic::class);

		$mock->shouldReceive("topic")
		->withAnyArgs()
		->andReturns($mockTopic);

		$mockTopic->shouldReceive("publish")
		->withAnyArgs()
		->andReturns(["messageid"]);

		$message = new Message(
			topic: "google",
			payload: "Ok",
			attributes: ["attr1" => "val1", "attr2" => "val2"],
			headers: ["header" => "value"]
		);

		$google = new Google($mock);
		$google->publish($message, ["opt1" => "val1", "opt2" => "val2"]);

		$mock->shouldHaveReceived(
			"topic",
			["google", ["opt1" => "val1", "opt2" => "val2"]]
		);

		$mockTopic->shouldHaveReceived(
			"publish",
			[
				["data" => "Ok", "attributes" => ["attr1" => "val1", "attr2" => "val2"]],
				["opt1" => "val1", "opt2" => "val2", "headers" => ["header" => "value"]]
			]
		);
	}

	public function test_publish_returns_receipt(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockTopic = Mockery::mock(Topic::class);

		$mock->shouldReceive("topic")
		->withAnyArgs()
		->andReturns($mockTopic);

		$mockTopic->shouldReceive("publish")
		->withAnyArgs()
		->andReturns(["afd1cbe8-6ee3-4de0-90f5-50c019a9a887"]);

		$message = new Message("google", "Ok");

		$google = new Google($mock);
		$receipt = $google->publish($message);

		$this->assertEquals(
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			$receipt
		);
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockTopic = Mockery::mock(Topic::class);

		$mock->shouldReceive("topic")
		->andReturns($mockTopic);

		$mockTopic->shouldReceive("publish")
		->andThrows(new Exception("Failure"));

		$message = new Message("google", "Ok");

		$google = new Google($mock);

		$this->expectException(PublishException::class);
		$google->publish($message);
	}

	public function test_consume_integration(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockSubscription = Mockery::mock(Subscription::class);

		$mock->shouldReceive("subscription")
		->andReturns($mockSubscription);

		$mockSubscription->shouldReceive("pull")
		->andReturns([
			new PubSubMessage(
				["data" => "message1", "attributes" => ["attr1" => "val1", "attr2" => "value2"]],
				["subscription" => $mockSubscription]
			)
		]);

		$mockSubscription->shouldReceive("name")
		->andReturns("google_subscription_name");

		$google = new Google($mock);
		$google->consume("google", 10, ["opt1" => "val1", "opt2" => "val2"]);

		$mockSubscription->shouldHaveReceived(
			"pull",
			[
				["maxMessages" => 10, "opt1" => "val1", "opt2" => "val2"]
			]
		);
	}

	public function test_consume(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockSubscription = Mockery::mock(Subscription::class);

		$mock->shouldReceive("subscription")
		->andReturns($mockSubscription);

		$mockSubscription->shouldReceive("pull")
		->andReturns([
			new PubSubMessage(
				["data" => "message1", "attributes" => ["attr1" => "val1", "attr2" => "value2"]],
				["subscription" => $mockSubscription]
			),

			new PubSubMessage(
				["data" => "message2", "attributes" => ["attr3" => "val3", "attr4" => "value4"]],
				["subscription" => $mockSubscription]
			),
		]);

		$mockSubscription->shouldReceive("name")
		->andReturns("google_subscription_name");

		$google = new Google($mock);
		$messages = $google->consume("google", 10);

		$this->assertCount(2, $messages);

		$this->assertEquals(
			"google_subscription_name",
			$messages[0]->getTopic()
		);

		$this->assertEquals(
			"message1",
			$messages[0]->getPayload()
		);

		$this->assertEquals(
			["attr1" => "val1", "attr2" => "value2"],
			$messages[0]->getAttributes()
		);

		$this->assertInstanceOf(
			PubSubMessage::class,
			$messages[0]->getReference()
		);

		$this->assertEquals(
			"google_subscription_name",
			$messages[1]->getTopic()
		);

		$this->assertEquals(
			"message2",
			$messages[1]->getPayload()
		);

		$this->assertEquals(
			["attr3" => "val3", "attr4" => "value4"],
			$messages[1]->getAttributes()
		);

		$this->assertInstanceOf(
			PubSubMessage::class,
			$messages[1]->getReference()
		);
	}

	public function test_consume_failure_throws_consume_exception(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockSubscription = Mockery::mock(Subscription::class);

		$mock->shouldReceive("subscription")
		->andReturns($mockSubscription);

		$mockSubscription->shouldReceive("pull")
		->andThrows(new Exception("Failure"));

		$google = new Google($mock);

		$this->expectException(ConsumeException::class);
		$google->consume("google", 10);
	}

	public function test_ack_integration(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockSubscription = Mockery::mock(Subscription::class);

		$mock->shouldReceive("subscription")
		->andReturns($mockSubscription);

		$mockSubscription->shouldReceive("acknowledge")
		->andReturns(["receiptid"]);

		$mockPubsubMessage = Mockery::mock(PubSubMessage::class);

		$google = new Google($mock);
		$google->ack(new Message(topic: "google", payload: "Ok", reference: $mockPubsubMessage));

		$mockSubscription->shouldHaveReceived(
			"acknowledge",
			[
				PubSubMessage::class
			]
		);
	}

	public function test_ack_failure_throws_consume_exception(): void
	{
		$mock = Mockery::mock(PubSubClient::class);
		$mockSubscription = Mockery::mock(Subscription::class);

		$mock->shouldReceive("subscription")
		->andReturns($mockSubscription);

		$mockSubscription->shouldReceive("acknowledge")
		->andThrows(new Exception("Failure"));

		$google = new Google($mock);

		$this->expectException(ConsumeException::class);
		$google->ack(new Message(topic: "google", payload: "Ok"));
	}

	public function test_nack(): void
	{
		$mock = Mockery::mock(PubSubClient::class);

		$google = new Google($mock);
		$result = $google->nack(new Message(topic: "google", payload: "Ok"));
		$this->assertNull($result);
	}
}