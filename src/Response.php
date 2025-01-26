<?php

namespace Nimbly\Syndicate;

/**
 * Use this Response enum in your handlers to let the Application know what
 * to do with the consumed message.
 */
enum Response
{
	case ack;
	case nack;
	case deadletter;
}