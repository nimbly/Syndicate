<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nimbly\Syndicate\Adapter\Segment;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Segment\Client;

/**
 * @covers Nimbly\Syndicate\Adapter\Segment
 */
class SegmentTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish_result_false_throws_publish_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("track")
			->andReturn(false);

		$publisher = new Segment($mock);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("track", "Ok", ["event" => "Foo", "userId" => "abc123"])
		);
	}

	public function test_publish_auto_flush_disabled(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("track")
			->andReturn(true);

		$publisher = new Segment($mock, false);

		$publisher->publish(
			new Message("track", "Ok", ["event" => "Foo", "userId" => "abc123"])
		);

		$mock->shouldHaveReceived("track");
		$mock->shouldNotHaveReceived("flush");
	}

	public function test_unsupported_topic_throws_publish_exception(): void
	{
		$mock = Mockery::mock(Client::class);
		$publisher = new Segment($mock);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("unsupported", "Ok", ["userId" => "abc123"])
		);
	}

	public function test_track_requires_event(): void
	{
		$mock = Mockery::mock(Client::class);
		$publisher = new Segment($mock);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("track", "Ok", ["userId" => "abc123"])
		);
	}

	public function test_track(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("track")
			->andReturn(true);
		$mock->shouldReceive("flush");

		$publisher = new Segment($mock);
		$publisher->publish(
			new Message(
				"track",
				\json_encode(["status" => "Ok"]),
				[
					"event" => "fruits",
					"userId" => "abc123"
				]
			)
		);

		$mock->shouldHaveReceived(
			"track",
			[
				[
					"userId" => "abc123",
					"event" => "fruits",
					"properties" => ["status" => "Ok"]
				]
			]
		);
		$mock->shouldHaveReceived("flush");
	}

	public function test_track_userid_or_anonymousid_required(): void
	{
		$mock = Mockery::mock(Client::class);
		$publisher = new Segment($mock);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("track", "Ok")
		);
	}

	public function test_identify(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("identify")
			->andReturn(true);
		$mock->shouldReceive("flush");

		$publisher = new Segment($mock);
		$publisher->publish(
			new Message(
				"identify",
				\json_encode(["status" => "Ok"]),
				[
					"userId" => "abc123"
				]
			)
		);

		$mock->shouldHaveReceived(
			"identify",
			[
				[
					"userId" => "abc123",
					"traits" => ["status" => "Ok"]
				]
			]
		);
		$mock->shouldHaveReceived("flush");
	}

	public function test_group_requires_group_id(): void
	{
		$mock = Mockery::mock(Client::class);
		$publisher = new Segment($mock);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("group", \json_encode(["name" => "grp"]))
		);
	}

	public function test_group(): void
	{
		$mock = Mockery::mock(Client::class);
		$mock->shouldReceive("group")
			->andReturn(true);
		$mock->shouldReceive("flush");

		$publisher = new Segment($mock);

		$publisher->publish(
			new Message(
				topic: "group",
				payload: \json_encode(["name" => "grp"]),
				attributes: [
					"groupId" => "2b44c921-6711-475e-8ae6-2188e59e5888",
					"userId" => "db92915a-334c-4051-9218-88eb7b049252"
				]
			)
		);

		$mock->shouldHaveReceived(
			"group",
			[
				[
					"userId" => "db92915a-334c-4051-9218-88eb7b049252",
					"groupId" => "2b44c921-6711-475e-8ae6-2188e59e5888",
					"traits" => [
						"name" => "grp"
					]
				]
			]
		);

		$mock->shouldHaveReceived("flush");
	}
}