<?php

namespace Nimbly\Syndicate\Tests\Adapters\PubSub;

use Mockery;
use Exception;
use Aws\Result;
use Aws\Sns\SnsClient;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\PubSub\Sns;
use Aws\Exception\CredentialsException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @covers Nimbly\Syndicate\Adapter\PubSub\Sns
 */
class SnsTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_integration(): void
	{
		$mock = Mockery::mock(SnsClient::class);

		$mock->shouldReceive("publish")
			->andReturns(new Result([
				"MessageId" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"
			]));

		$message = new Message("sns_topic", "Ok", ["attr1" => "val1", "attr2" => "val2"]);

		$publisher = new Sns($mock);
		$publisher->publish($message, ["MessageGroupId" => "group", "MessageDeduplicationId" => "dedupe", "opt1" => "val1", "opt2" => "val2"]);

		$mock->shouldHaveReceived(
			"publish",
			[
				[
					"TopicArn" => "sns_topic",
					"Data" => "Ok",
					"MessageAttributes" => ["attr1" => "val1", "attr2" => "val2"],
					"MessageGroupId" => "group",
					"MessageDeduplicationId" => "dedupe",
					"opt1" => "val1",
					"opt2" => "val2"
				]
			]
		);
	}

	public function test_publish_returns_receipt(): void
	{
		$mock = Mockery::mock(SnsClient::class);

		$mock->shouldReceive("publish")
			->andReturns(new Result([
				"MessageId" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887"
			]));

		$message = new Message("sns_topic", "Ok");

		$publisher = new Sns($mock);
		$receipt = $publisher->publish($message);

		$this->assertEquals(
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			$receipt
		);
	}

	public function test_publish_credentials_exception_throws_connection_exception(): void
	{
		$mock = Mockery::mock(SnsClient::class);

		$mock->shouldReceive("publish")
			->andThrows(new CredentialsException("Failure"));

		$message = new Message("sns_topic", "Ok");

		$publisher = new Sns($mock);

		$this->expectException(ConnectionException::class);
		$publisher->publish($message);
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$mock = Mockery::mock(SnsClient::class);

		$mock->shouldReceive("publish")
			->andThrows(new Exception("Failure"));

		$message = new Message("sns_topic", "Ok");

		$publisher = new Sns($mock);

		$this->expectException(PublishException::class);
		$publisher->publish($message);
	}
}