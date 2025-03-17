<?php

namespace Nimbly\Syndicate\Tests\Adapter;

use Mockery;
use Exception;
use ReflectionObject;
use Nimbly\Capsule\Response;
use Nimbly\Syndicate\Message;
use PHPUnit\Framework\TestCase;
use Nimbly\Syndicate\Adapter\Azure;
use Nimbly\Syndicate\Exception\ConsumeException;
use Nimbly\Syndicate\Exception\PublishException;
use Nimbly\Syndicate\Exception\ConnectionException;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use MicrosoftAzure\Storage\Common\Internal\Resources;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MicrosoftAzure\Storage\Queue\Models\ListMessagesResult;
use MicrosoftAzure\Storage\Queue\Models\CreateMessageResult;
use MicrosoftAzure\Storage\Queue\Models\ListMessagesOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Azure::class)]
class AzureTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function test_publish(): void
	{
		$message = new Message("azure", "Ok");

		$client = Mockery::mock(QueueRestProxy::class);

		$client->shouldReceive("createMessage")
			->andReturns(
				CreateMessageResult::create([
					"QueueMessage" => [
						"ExpirationTime" => \date(Resources::AZURE_DATE_FORMAT, \strtotime("+60 seconds")),
						"InsertionTime" => \date(Resources::AZURE_DATE_FORMAT),
						"TimeNextVisible" => \date(Resources::AZURE_DATE_FORMAT, \strtotime("+1 minute")),
						"MessageId" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
						"PopReceipt" => "0be31d6e-0b46-43d4-854c-772e7d717ce5"
					]
				])
			);

		$publisher = new Azure($client);

		$receipt_id = $publisher->publish($message);

		$client->shouldHaveReceived(
			"createMessage",
			[$message->getTopic(), $message->getPayload()]
		);

		$this->assertEquals(
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			$receipt_id
		);
	}

	public function test_publish_service_exception_throws_connection_exception(): void
	{
		$message = new Message("azure", "Ok");

		$client = Mockery::mock(QueueRestProxy::class);

		$client->shouldReceive("createMessage")
			->andThrows(new ServiceException(new Response(400)));

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);

		$this->expectException(ConnectionException::class);
		$publisher->publish($message);
	}

	public function test_publish_failure_throws_publish_exception(): void
	{
		$message = new Message("azure", "Ok");

		$client = Mockery::mock(QueueRestProxy::class);

		$client->shouldReceive("createMessage")
			->andThrows(new Exception("Failure"));

		$publisher = new Azure($client);

		$this->expectException(PublishException::class);
		$publisher->publish($message);
	}

	public function test_consume(): void
	{
		$client = Mockery::mock(QueueRestProxy::class);

		$client->shouldReceive("listMessages")
			->andReturns(
				ListMessagesResult::create(
					[
						"QueueMessage" => [
							[
								"ExpirationTime" => \date(Resources::AZURE_DATE_FORMAT, \strtotime("+60 seconds")),
								"InsertionTime" => \date(Resources::AZURE_DATE_FORMAT),
								"TimeNextVisible" => \date(Resources::AZURE_DATE_FORMAT, \strtotime("+1 minute")),
								"MessageId" => "afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
								"PopReceipt" => "0be31d6e-0b46-43d4-854c-772e7d717ce5",
								"DequeueCount" => 12,
								"MessageText" => "Message1"
							],

							[
								"ExpirationTime" => \date(Resources::AZURE_DATE_FORMAT, \strtotime("+30 seconds")),
								"InsertionTime" => \date(Resources::AZURE_DATE_FORMAT),
								"TimeNextVisible" => \date(Resources::AZURE_DATE_FORMAT, \strtotime("+5 minute")),
								"MessageId" => "022f6eba-d374-4ebf-a8bf-f4faccf95afe",
								"PopReceipt" => "596a4980-488f-4e4f-a078-745c3b66cc95",
								"DequeueCount" => 9,
								"MessageText" => "Message2"
							]
						]
					]
				)
			);

		$publisher = new Azure($client);
		$messages = $publisher->consume("azure", 10, ["timeout" => 25, "delay" => 20]);

		$client->shouldHaveReceived(
			"listMessages",
			["azure", anyArgs()]
		);

		$this->assertCount(2, $messages);

		$this->assertEquals(
			"azure",
			$messages[0]->getTopic()
		);

		$this->assertEquals(
			"Message1",
			$messages[0]->getPayload()
		);

		$this->assertEquals(
			["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"],
			$messages[0]->getReference()
		);

		$this->assertEquals(
			"azure",
			$messages[1]->getTopic()
		);

		$this->assertEquals(
			"Message2",
			$messages[1]->getPayload()
		);

		$this->assertEquals(
			["022f6eba-d374-4ebf-a8bf-f4faccf95afe", "596a4980-488f-4e4f-a078-745c3b66cc95"],
			$messages[1]->getReference()
		);
	}

	public function test_consume_service_exception_throws_connection_exception(): void
	{
		$client = Mockery::mock(QueueRestProxy::class);

		$client->shouldReceive("listMessages")
			->andThrows(new ServiceException(new Response(500)));

		$publisher = new Azure($client);

		$this->expectException(ConnectionException::class);
		$publisher->consume("azure");
	}

	public function test_consume_failure_throws_consume_exception(): void
	{
		$client = Mockery::mock(QueueRestProxy::class);

		$client->shouldReceive("listMessages")
			->andThrows(new Exception("Failure"));

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);

		$this->expectException(ConsumeException::class);
		$publisher->consume("azure");
	}

	public function test_build_list_message_options(): void
	{
		$client = Mockery::mock(QueueRestProxy::class);

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);

		$reflectionObject = new ReflectionObject($publisher);
		$reflectionMethod = $reflectionObject->getMethod("buildListMessageOptions");
		$reflectionMethod->setAccessible(true);

		/**
		 * @var ListMessagesOptions $listMessageOptions
		 */
		$listMessageOptions = $reflectionMethod->invoke($publisher, 10, ["delay" => 20, "timeout" => 25]);

		$this->assertEquals(
			10,
			$listMessageOptions->getNumberOfMessages()
		);

		$this->assertEquals(
			20,
			$listMessageOptions->getVisibilityTimeoutInSeconds()
		);

		$this->assertEquals(
			25,
			$listMessageOptions->getTimeout()
		);
	}

	public function test_ack(): void
	{
		$message = new Message(
			topic: "azure",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$client = Mockery::spy(QueueRestProxy::class);

		$publisher = new Azure($client);
		$publisher->ack($message);

		$client->shouldHaveReceived(
			"deleteMessage",
			[
				$message->getTopic(),
				"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
				"0be31d6e-0b46-43d4-854c-772e7d717ce5"
			]
		)->once();
	}

	public function test_ack_service_exception_throws_connection_exception(): void
	{
		$message = new Message(
			topic: "azure",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$client = Mockery::mock(QueueRestProxy::class);
		$client->shouldReceive("deleteMessage")
			->andThrows(new ServiceException(new Response(500)));

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);

		$this->expectException(ConnectionException::class);
		$publisher->ack($message);
	}

	public function test_ack_failure_throws_consume_exception(): void
	{
		$message = new Message(
			topic: "azure",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$client = Mockery::mock(QueueRestProxy::class);
		$client->shouldReceive("deleteMessage")
			->andThrows(new Exception("Failure"));

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);

		$this->expectException(ConsumeException::class);
		$publisher->ack($message);
	}

	public function test_nack(): void
	{
		$message = new Message(
			topic: "azure",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$client = Mockery::spy(QueueRestProxy::class);

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);
		$publisher->nack($message, 12);

		$client->shouldHaveReceived(
			"updateMessage",
			[
				$message->getTopic(),
				"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
				"0be31d6e-0b46-43d4-854c-772e7d717ce5",
				"Ok",
				12
			]
		)
		->once();
	}

	public function test_nack_service_exception_throws_connection_exception(): void
	{
		$message = new Message(
			topic: "azure",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$client = Mockery::mock(QueueRestProxy::class);
		$client->expects()
		->updateMessage(
			$message->getTopic(),
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			"0be31d6e-0b46-43d4-854c-772e7d717ce5",
			$message->getPayload(),
			0
		)
		->andThrows(new ServiceException(new Response(500)));

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);

		$this->expectException(ConnectionException::class);
		$publisher->nack($message);
	}

	public function test_nack_failure_throws_consume_exception(): void
	{
		$message = new Message(
			topic: "azure",
			payload: "Ok",
			reference: ["afd1cbe8-6ee3-4de0-90f5-50c019a9a887", "0be31d6e-0b46-43d4-854c-772e7d717ce5"]
		);

		$client = Mockery::mock(QueueRestProxy::class);
		$client->expects()
		->updateMessage(
			$message->getTopic(),
			"afd1cbe8-6ee3-4de0-90f5-50c019a9a887",
			"0be31d6e-0b46-43d4-854c-772e7d717ce5",
			$message->getPayload(),
			0
		)
		->andThrows(new Exception("Failure"));

		/**
		 * @var QueueRestProxy $client
		 */
		$publisher = new Azure($client);

		$this->expectException(ConsumeException::class);
		$publisher->nack($message);
	}
}