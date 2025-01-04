<?php

namespace Nimbly\Syndicate;

enum Response
{
	case ack;
	case nack;
	case deadletter;
}