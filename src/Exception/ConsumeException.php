<?php

namespace Nimbly\Syndicate\Exception;

use Exception;

/**
 * This exception is thrown when attempting to consume a message
 * from a consumer or subscriber.
 */
class ConsumeException extends Exception
{}