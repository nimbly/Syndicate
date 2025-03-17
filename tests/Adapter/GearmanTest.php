<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use GearmanClient;
use GearmanWorker;
use Nimbly\Syndicate\Adapter\Gearman;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\SubscriptionException;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use UnexpectedValueException;

#[CoversClass(Gearman::class)]
class GearmanTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_no_client_or_worker_throws_unexpected_value_exception(): void
	{
		$this->expectException(UnexpectedValueException::class);
		new Gearman;
	}

	public function test_publish_with_no_client_throws_publish_exception(): void
	{
		$mock = Mockery::mock(GearmanWorker::class);

		$publisher = new Gearman(worker: $mock);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("fruits", "bananas")
		);
	}

	public function test_publish_normal_priority(): void
	{
		$mock = Mockery::mock(GearmanClient::class);

		$mock->shouldReceive("doBackground")
			->andReturns("1776f45b-39b9-411c-b794-326067f00be5");

		$publisher = new Gearman(client: $mock);

		$receipt = $publisher->publish(
			new Message("fruits", "bananas")
		);

		$this->assertEquals(
			"1776f45b-39b9-411c-b794-326067f00be5",
			$receipt
		);

		$mock->shouldHaveReceived(
			"doBackground",
			["fruits", "bananas"]
		);
	}

	public function test_publish_low_priority(): void
	{
		$mock = Mockery::mock(GearmanClient::class);

		$mock->shouldReceive("doLowBackground")
			->andReturns("1776f45b-39b9-411c-b794-326067f00be5");

		$publisher = new Gearman(client: $mock);

		$publisher->publish(
			new Message(topic: "fruits", payload: "bananas", attributes: ["priority" => "low"])
		);

		$mock->shouldHaveReceived(
			"doLowBackground",
			["fruits", "bananas"]
		);
	}

	public function test_publish_high_priority(): void
	{
		$mock = Mockery::mock(GearmanClient::class);

		$mock->shouldReceive("doHighBackground")
			->andReturns("1776f45b-39b9-411c-b794-326067f00be5");

		$publisher = new Gearman(client: $mock);

		$publisher->publish(
			new Message(topic: "fruits", payload: "bananas", attributes: ["priority" => "high"])
		);

		$mock->shouldHaveReceived(
			"doHighBackground",
			["fruits", "bananas"]
		);
	}

	public function test_subscribe_with_no_worker_throws_subscription_exception(): void
	{
		$mock = Mockery::mock(GearmanClient::class);
		$subscriber = new Gearman(client: $mock);

		$this->expectException(SubscriptionException::class);
		$subscriber->subscribe("fruits", "strtolower");
	}

	public function test_subscribe_with_multiple_topics_array(): void
	{
		$mock = Mockery::mock(GearmanWorker::class);
		$mock->shouldReceive("addFunction")
			->andReturn(true);

		$subscriber = new Gearman(worker: $mock);
		$subscriber->subscribe(["fruits", "veggies"], "strtolower", ["timeout" => 5]);

		$mock->shouldHaveReceived("addFunction")->twice();
	}

	public function test_subscribe_with_multiple_topics_comma_separated(): void
	{
		$mock = Mockery::mock(GearmanWorker::class);
		$mock->shouldReceive("addFunction")
			->andReturn(true);

		$subscriber = new Gearman(worker: $mock);
		$subscriber->subscribe("fruits, veggies", "strtolower", ["timeout" => 5]);

		$mock->shouldHaveReceived("addFunction")->twice();
	}

	public function test_subscribe_failure_throws_subscription_exception(): void
	{
		$mock = Mockery::mock(GearmanWorker::class);
		$mock->shouldReceive("addFunction")
			->andReturn(false);

		$subscriber = new Gearman(worker: $mock);

		$this->expectException(SubscriptionException::class);
		$subscriber->subscribe("fruits", "strtolower");
	}

	public function test_loop_no_worker_throws_consume_exception(): void
	{
		$mock = Mockery::mock(GearmanClient::class);
		$subscriber = new Gearman(client: $mock);

		$this->expectException(ConsumeException::class);
		$subscriber->loop();
	}

	public function test_loop_exits_on_unsuccessful_return_code(): void
	{
		$mock = Mockery::mock(GearmanWorker::class);
		$mock->shouldReceive("work")->andReturn(true);
		$mock->shouldReceive("returnCode")->andReturn(-1);

		$subscriber = new Gearman(worker: $mock);
		$subscriber->loop();

		$mock->shouldHaveReceived("work");
		$mock->shouldHaveReceived("returnCode");
	}

	public function test_loop_exits_on_shutdown(): void
	{
		$mock = Mockery::mock(GearmanWorker::class);
		$subscriber = new Gearman(worker: $mock);


		$mock->shouldReceive("work")->andReturnUsing(
			function() use ($subscriber) {
				$subscriber->shutdown();
				return true;
			}
		);
		$mock->shouldReceive("returnCode")->andReturn(0);

		$subscriber->loop();

		$mock->shouldHaveReceived("work");
		$mock->shouldHaveReceived("returnCode");
	}

	public function test_shutdown(): void
	{
		$mock = Mockery::mock(GearmanWorker::class);
		$subscriber = new Gearman(worker: $mock);

		$reflectionClass = new ReflectionClass($subscriber);
		$reflectionProperty = $reflectionClass->getProperty("running");
		$reflectionProperty->setAccessible(true);
		$reflectionProperty->setValue($subscriber, true);

		$this->assertTrue($reflectionProperty->getValue($subscriber));

		$subscriber->shutdown();

		$this->assertFalse($reflectionProperty->getValue($subscriber));
	}
}