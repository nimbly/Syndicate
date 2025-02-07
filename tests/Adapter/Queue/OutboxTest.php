<?php

namespace Nimbly\Syndicate\Tests\Adapter\Queue;

use PDO;
use Nimbly\Syndicate\Adapter\Queue\Outbox;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;

/**
 * @covers Nimbly\Syndicate\Adapter\Queue\Outbox
 */
class OutboxTest extends TestCase
{
	public function test_publish_prepare_failure_throws_publish_exception(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$publisher = new Outbox($pdo);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);
	}

	public function test_publish_prepare_failure_throws_publish_exception_if_statement_is_false(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

		$publisher = new Outbox($pdo);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);
	}

	public function test_publish_execute_failure_throws_publish_exception(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->query("create table outbox (id uuid primary key, topic text, payload text, headers text, attributes text, created_at timestamp)");

		$publisher = new Outbox(
			pdo: $pdo,
			identity_generator: fn() => "a822a65c-eb88-46d5-917c-42c9651e5f03"
		);

		$publisher->publish(
			new Message("fruits", "oranges", ["attr" => "value"], ["header" => "value"])
		);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);
	}

	public function test_publish_execute_failure_throws_publish_exception_if_execute_returns_false(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		$pdo->query("create table outbox (id uuid primary key, topic text, payload text, headers text, attributes text, created_at timestamp)");

		$publisher = new Outbox(
			pdo: $pdo,
			identity_generator: fn() => "a822a65c-eb88-46d5-917c-42c9651e5f03"
		);

		$publisher->publish(
			new Message("fruits", "oranges", ["attr" => "value"], ["header" => "value"])
		);

		$this->expectException(PublishException::class);
		$publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);
	}

	public function test_publish_with_custom_table_name(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->query("create table messages (id integer primary key, topic text, payload text, headers text, attributes text, created_at timestamp)");

		$publisher = new Outbox(
			pdo: $pdo,
			table: "messages"
		);

		$publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);

		$statement = $pdo->query("select * from messages");
		$messages = $statement->fetchAll(PDO::FETCH_ASSOC);

		$this->assertCount(1, $messages);
	}

	public function test_publish_with_custom_identity_generator(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->query("create table outbox (id uuid primary key, topic text, payload text, headers text, attributes text, created_at timestamp)");

		$publisher = new Outbox(
			pdo: $pdo,
			identity_generator: fn() => "a822a65c-eb88-46d5-917c-42c9651e5f03"
		);

		$receipt = $publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);

		$this->assertEquals(
			"a822a65c-eb88-46d5-917c-42c9651e5f03",
			$receipt
		);
	}

	public function test_publish_returns_last_insert_id(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->query("create table outbox (id integer primary key, topic text, payload text, headers text, attributes text, created_at timestamp)");

		$publisher = new Outbox(
			pdo: $pdo,
		);

		$receipt = $publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);

		$this->assertEquals(1, $receipt);
	}

	public function test_publish_inserts_correct_values(): void
	{
		$pdo = new PDO("sqlite::memory:");
		$pdo->query("create table outbox (id integer primary key, topic text, payload text, headers text, attributes text, created_at timestamp)");

		$publisher = new Outbox(
			pdo: $pdo,
		);

		$publisher->publish(
			new Message("fruits", "bananas", ["attr" => "value"], ["header" => "value"])
		);

		$statement = $pdo->query("select * from outbox");
		$messages = $statement->fetchAll(PDO::FETCH_ASSOC);

		$this->assertCount(1, $messages);
		$this->assertEquals(1, $messages[0]["id"]);
		$this->assertEquals("fruits", $messages[0]["topic"]);
		$this->assertEquals("bananas", $messages[0]["payload"]);
		$this->assertEquals("{\"attr\":\"value\"}", $messages[0]["attributes"]);
		$this->assertEquals("{\"header\":\"value\"}", $messages[0]["headers"]);
		$this->assertTrue(
			(bool) \preg_match("/^\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}\:\d{2}/", $messages[0]["created_at"])
		);
	}
}