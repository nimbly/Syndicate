<?php

namespace Nimbly\Syndicate\Exception;

use Exception;

/**
 * This exception is thrown when attempting to subscribe to a
 * topic. Only `SubscriberInterface` adapters throw this
 * exception.
 */
class SubscriptionException extends Exception
{}