<?php

namespace Nimbly\Syndicate\Exception;

use Exception;

/**
 * This exception is thrown when the underlying connection to the remote
 * source for a publisher, consumer, or subscriber has failed.
 */
class ConnectionException extends Exception
{}