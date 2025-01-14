<?php

namespace Nimbly\Syndicate\Examples\Handlers;

use Nimbly\Syndicate\Consume;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;

class ExampleHandler
{
	/**
	 * Handle all messages about bananas.
	 *
	 * @param Message $message
	 * @return Response|null
	 */
	#[Consume(
		topic: "fruits",
		payload: ["$.name" => "bananas"]
	)]
	public function onBananas(Message $message): ?Response
	{
		$payload = \json_decode($message->getPayload(), false);

		echo \sprintf(
			"[%s] Received message: %s\n",
			$payload->name,
			$message->getPayload()
		);

		if( \mt_rand(1, 10) > 9 ) {
			return Response::nack;
		}

		return Response::ack;
	}

	/**
	 * Handle all messages about kiwis.
	 *
	 * @param Message $message
	 * @return Response|null
	 */
	 #[Consume(
		topic: "fruits",
		payload: ["$.name" => "kiwis"]
	)]
	public function onKiwis(Message $message): ?Response
	{
		$payload = \json_decode($message->getPayload(), false);

		echo \sprintf(
			"[%s] Received message: %s\n",
			$payload->name,
			$message->getPayload()
		);

		if( \mt_rand(1, 10) > 9 ) {
			return Response::nack;
		}

		return Response::ack;
	}

	/**
	 * Handle all messages about oranges.
	 *
	 * @param Message $message
	 * @return Response|null
	 */
	#[Consume(
		topic: "fruits",
		payload: ["$.name" => "oranges"]
	)]
	public function onOranges(Message $message): ?Response
	{
		$payload = \json_decode($message->getPayload(), false);

		echo \sprintf(
			"[%s] Received message: %s\n",
			$payload->name,
			$message->getPayload()
		);

		if( \mt_rand(1, 10) > 9 ) {
			return Response::nack;
		}

		return Response::ack;
	}

	/**
	 * Handle all messages about mangoes.
	 *
	 * @param Message $message
	 * @return Response|null
	 */
	#[Consume(
		topic: "fruits",
		payload: ["$.name" => ["mangoes", "mangos"]]
	)]
	public function onMangoes(Message $message): ?Response
	{
		$payload = \json_decode($message->getPayload(), false);

		echo \sprintf(
			"[%s] Received message: %s\n",
			$payload->name,
			$message->getPayload()
		);

		if( \mt_rand(1, 10) > 9 ) {
			return Response::nack;
		}

		return Response::ack;
	}
}