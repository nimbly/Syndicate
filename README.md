# Syndicate

[![Latest Stable Version](https://img.shields.io/packagist/v/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/nimbly/syndicate/test.yml?style=flat-square)](https://github.com/nimbly/Syndicate/actions/workflows/test.yml)
[![Codecov branch](https://img.shields.io/codecov/c/github/nimbly/syndicate/master?style=flat-square)](https://app.codecov.io/github/nimbly/Syndicate)
[![License](https://img.shields.io/github/license/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)

Syndicate is a powerful framework able to both publish and consume messages - ideal for your event driven application or as a job processor. It supports common queues and PubSub integrations with an `Application` layer that can be used to route incoming messages to any handler of your choosing with full dependency injection using a PSR-11 Container instance.

## Requirements

* PHP 8.2+
* php-pcntl (process control)

## Suggested

* PSR-11 Container implementation

## Uses cases

* Publish messages to a queue, pubsub topic, or webhook
* Event message processing
* Background job processing
* General queue message processing

## Supported implementations

### Queues

| Implementation | Publisher | Consumer | Library |
| -------------- | --------- | -------- | ------- |
| Redis          | Y         | Y        | `predis/predis:^2.0` |
| Azure          | Y         | Y        | `microsoft/azure-storage-queue:^1.3` |
| SQS            | Y         | Y        | `aws/aws-sdk-php:^3.336` |
| Beanstalk      | Y         | Y        | `pda/pheanstalk:^5.0` |
| IronMQ         | Y         | Y        | `iron-io/iron_mq:^4.0` |
| RabbitMQ       | Y         | Y        | `php-amqplib/php-amqplib:^3.7` |

### PubSubs

| Implementation | Publisher | Consumer | Library |
| -------------- | --------- | -------- | ------- |
| Redis          | Y         | Loop     | `predis/predis:^2.0` |
| SNS            | Y         | N        | `aws/aws-sdk-php:^3.336` |
| MQTT           | Y         | Loop     | `php-mqtt/client:^1.1` |
| Google         | Y         | Y        | `google/cloud-pubsub:^2.0` |
| Webhook        | Y         | N        | n/a |


Is there an implementation you would like to see supported? Let us know in [Github Discussions](https://github.com/nimbly/Syndicate/discussions) or open a Pull Request!

Alternatively, you can implement your own consumers and publishers by adhering to the `Nimbly\Syndicate\ConsumerInterface` and/or `Nimbly\Syndicate\PublisherInterface` interfaces.

## Installation

```bash
composer require nimbly/syndicate
```

## Quick start, publisher

A publisher sends (aka publishes) messages to a known location like a queue or to a PubSub topic.

Select an integration you would like to publish messages to. In this example, we will be publishing messags to an SNS topic.

```php
$publisher = new Sns(
	client: new SnsClient(["region" => "us-west-2", "version" => "latest"]),
);

$message = new Message(
	topic: "arn:aws:sns:us-west-2:010393102219:orders",
	payload: \json_encode($order)
);

$publisher->publish($message);
```

## Quick start, Application

```php
$consumer = new Sqs(
	new SqsClient([
		"region" => "us-west-2",
		"version" => "latest"
	])
);

$application = new Application(
	consumer: $client,
	router: new Router([
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\OrdersHandler::class,
	]),
	deadletter: new DeadletterPublisher(
		$consumer,
		"https://sqs.us-west-2.amazonaws.com/123456789012/deadletter",
	),
	container: $container,
	signals: [SIGINT, SIGTERM, SIGHUP]
)
```

To start consuming messages, call the `listen` method on the application instance.


```php
$application->listen(
	location: "https://sqs.us-west-2.amazonaws.com/123456789012/MyQueue",
	max_messages: 10,
	nack_timeout: 12,
	polling_timeout: 5
);
```

The `listen` method will continue to poll for new messages and route them to your handlers. To shutdown the listener, you must send an interrupt signal that was defined in the `Application` constructor, typically SIGINT (ctrl-c), SIGHUP, SIGTERM, etc.

The `location` parameter is the topic name, queue name, or queue URL you will be listening on. This parameter value is dependent on which consumer implementation you are using.

The `max_messages` parameter defines how many messages should be pulled off at a single time. Some implementations only allow a single message at a time.

The `nack_timeout` parameter defines how long (in minutes) the message should be held before it can be pulled again when a `Response::nack` is returned by your handler. Some implementations do not support modifying the message visibility timeout.

And finally, the `polling_timeout` parameter defines how long (in seconds) the consumer implementation should block waiting for messages before disconnecting and trying again.

Now, let's dig deeper into each of the constructor parameters for the `Application` instance.

### Consumer

The `consumer` parameter is any instance of `Nimbly\Syndicate\ConsumerInterface` - the source where messages should be pulled from.

```php
$consumer = new Sqs(
	new SqsClient([
		"region" => "us-west-2",
		"version" => "latest"
	])
);
```

### Router

The `router` parameter is an instance of `Nimbly\Syndicate\Router` (or any instance of `Nimbly\Syndicate\RouterInterface`). This router relies on your handlers using the `Nimbly\Syndicate\Consume` attribute. Simply add a `#[Consume]` attribute with your routing criteria before your class methods on your handlers (please see **Handlers** and **Consume Attribute** sections for more documentation). Finally, pass these class names off to the `Router` instance.

```php
$router = new Router(
	handlers: [
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\OrdersHandler::class
	]
);
```

The `Router` class also supports an optional `default` handler. This is any `callable` that will be used if no matching routes could be found.

```php
$router = new Router(
	handlers: [
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\OrdersHandler::class,
	],
	default: function(Message $message): Response {
		// do something with message that could not be routed

		if( $foo ){
			return Response::deadletter;
		}

		return Response::ack;
	}
);
```

### Deadletter

The `deadletter` parameter allows you to define a deadletter location: a place to put messages that cannot be processed for whatever reason. The `deadletter` is simply a `PublisherInterface` instance. However, a helper is provided with the `DeadletterPublisher` class.

```php
$redis = new Nimbly\Syndicate\Queue\Redis(new Client);

$deadletter = new DeadletterPublisher(
	$redis,
	"foo_service_deadletter"
);
```

In this example, we would like to use a Redis queue for our deadletters and to push them into the `foo_service_deadletter` queue.

The `deadletter` implementation is used any time a message could not be routed and no default handler was provided *or* if you explicitly return `Response::deadletter` from your event handler.

### Container

The `container` parameter allows you to pass along a PSR-11 Container instance to be used in autowiring and dependency injection when calling your message handlers.

The `Nimbly\Syndicate\Message` instance can *always* be resolved without the need of a conatiner.

```php
class UsersHandler
{
	#[Consume(
		payload: ["$.event" => "UserRegistered"]
	)]
	public function onUserRegistered(Message $message, EmailService $email): Response
	{
		$body = \json_decode($message->getPayload());

		$result = $email->send(
			$body->payload->email,
			$body->payload->name,
			"templates/registration.tpl"
		);

		if( $result === false ){
			return Response::nack;
		}

		return Response::ack;
	}
}
```

In this example, both the `Message` and the `EmailService` dependecies are injected - assuming the container has the `EmailService` instance in it.

### Signals

The `signals` parameter is an array of PHP interrupt signal constants (eg, `SIGINT`, `SIGTERM`, etc) that you would like your application to respond to and gracefully shutdown the application. I.e. once all messages in the batch have been processed by your handlers, the application will terminate.

If no signals are caught, any interrupt signal received will force an immediate shutdown, even if in the middle of processing a message which could lead to unintended outcomes.

### LoggerInterface

The `logger` parameter allows you to pass a `Psr\Log\LoggerInterface` instance to the application. Syndicate will use this logger instance to log messages to give you better visibility into your application.

## Handlers

A handler is your code that will receive the event message and process it. The handler can be any `callable` type but typically is a class method.

`Syndicate` will call your handlers with full dependency resolution and injection with a PSR-11 Container instance you have provided. Both the constructor and the method to be called will have dependencies automatically resolved and injected.

**NOTE:** The `Nimbly\Syndicate\Message` instance can *always* be resolved with or without a conatiner.

```php
namespace App\Consumer\Handlers;

use App\Services\EmailService;
use Nimbly\Syndicate\Consume;
use Nimbly\Syndicate\Message;
use Nimbly\Syndicate\Response;


class UsersHandler
{
	public function __construct(
		protected LoggerInterface $logger
	)
	{
	}

	#[Consume(
		topic: "users",
		payload: ["$.event" => "UserCreated"]
	)]
	public function onUserRegistered(Message $message, EmailService $email): Response
	{
		// Do something with the message

		$this->logger->debug("Received UserCreated message.");
	}

	#[Consume(
		topic: "users",
		payload: ["$.event" => "UserDeleted"]
	)]
	public function onUserDeleted(Message $message): Response
	{
		// Do something with the message

		$this->logger->debug("Received UserDeleted message.");
	}
}
```

### Consume Attribute

The `#[Consume]` attribute allows you to add routing criteria/filters to your handlers. The criteria supported are:

* `topic` The topic name or an array of names.
* `payload` An array of key/value pair of JSON Path statements to a string or array of strings to match.
* `headers` An array of key/value pair of header names to a string or array of strings to match.
* `attributes` An array of key/value pair of attribute names to a string or array of strings to match.

You can have as many or as few routing criteria as you like. You may also use an **asterisk** as a wildcard for matching. Each type of criteria you add is ANDed together.

**NOTE:** In order to use the `payload` filter, your message content **must** be in JSON.

#### Examples

Here is an example where the topic must match exactly `users` **AND** the message body must have a `type` property that is exactly `UserCreated` **AND** the `body.role` property is either `user` **OR** `admin`.

```php
#[Consume(
	topic: "users",
	payload: ["$.type" => "UserCreated", "$.body.role" => ["user", "admin"]],
)]
```

Here is an example where the topic must start with `users/` **AND** the payload `type` property either starts with `User` **OR** starts with `Admin` **AND** the payload `body.role` property is either `user` **OR** `admin`.

```php
#[Consume(
	topic: ["users/*"],
	payload: ["$.type" => ["User*", "Admin*"], "$.body.role" => ["user", "admin"]],
)]
```

In this example, the `Origin` header will match as long as it ends with `/Syndicate` **OR** begins with `Deadletter/`.

```php
#[Consume(
	headers: ["Origin" => ["*/Syndicate", "Deadletter/*"]]
)]
```

### Response

After processing a message in your handler, you may return a `Nimbly\Syndicate\Response` enum to explicity declare what should be done with the message.

Possible response enum values:

* `Response::ack` - Acknowledge the message (removes the message from the source)
* `Response::nack` - Do not acknowledge the message (the message will be made availble again for processing after a short time, also known as releasing the message)
* `Response::deadletter` - Move the message to a separate deadletter location, provided in the `Application` constructor

**NOTE:** If no response value is returned by the handler (eg, `null` or `void`), or the response value is not one of `Response::nack` or `Response::deadletter` it is assumed the message should be `ack`ed. Best practice is to be explicit and always return a `Response` enum value.

```php
public function onUserRegistered(Message $message, EmailService $email): Response
{
	$payload = \json_decode($message->getPayload());

	// There is something fundamentally wrong with this message.
	// Let's push to the deadletter and investigate later.
	if( \json_last_error() !== JSON_ERROR_NONE ){
		return Response::deadletter;
	}

	$receipt_id = $email->send(
		$payload->user_name,
		$payload->user_email,
		"templates/registration.tpl"
	);

	// Email send failed, let's try again later...
	if( $receipt_id === null ){
		return Response::nack;
	}

	// All good!
	return Response::ack;
}
```

## Custom router

Although using the `#[Consume]` attribute is the fastest and easiest way to get your message handlers registered with the application router, you may want to implement your own custom  routing solution. Syndicate provides a `Nimbly\Syndicate\RouterInterface` for you to implement.

## Custom publishers and consumers

If you find that `Syndicate` does not support a particular publisher or consumer, we'd love to see a [Github Issues](https://github.com/nimbly/Syndicate/issues) opened or a message posted in [Github Discussions](https://github.com/nimbly/Syndicate/discussions).

Alternatively, you can create your own implementation using the `Nimbly\Syndicate\PublisherInterface` and/or the `Nimbly\Syndicate\ConsumerInterface`.

If you feel like sharing your implementations, we encourage you opening up a PR!