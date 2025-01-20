<?php

namespace Nimbly\Syndicate\Tests\Fixtures;

use Nimbly\Syndicate\Consume;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;

class TestHandler
{
	#[Consume(
		topic: "users",
		payload: ["$.event" => "UserCreated"],
		attributes: ["role" => "user"],
		headers: ["origin" => "value"]
	)]
	public function onUserCreated(Message $message): Response
	{
		return Response::ack;
	}

	#[Consume(
		topic: "admins",
		payload: ["$.event" => "AdminDeleted"],
		attributes: ["role" => "admin"],
		headers: ["origin" => "value"]
	)]
	public function onAdminDeleted(Message $message): Response
	{
		return Response::ack;
	}

	protected function classHelper(): void
	{
	}


	#[Consume(
		topic: "fruits"
	)]
	public function onFruits(Message $message): Response
	{
		return Response::ack;
	}
}