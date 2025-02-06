<?php

namespace Nimbly\Syndicate\Tests\Adapter\PubSub;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use GearmanClient;
use GearmanWorker;
use Nimbly\Syndicate\Adapter\PubSub\Gearman;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Message;
use UnexpectedValueException;

/**
 * @covers Nimbly\Syndicate\Adapter\PubSub\Gearman
 */
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
			new Message("fruits", "bananas"),
			["priority" => "low"]
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
			new Message("fruits", "bananas"),
			["priority" => "high"]
		);

		$mock->shouldHaveReceived(
			"doHighBackground",
			["fruits", "bananas"]
		);
	}
}