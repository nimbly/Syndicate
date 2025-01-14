# Syndicate

[![Latest Stable Version](https://img.shields.io/packagist/v/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/nimbly/syndicate/php.yml?style=flat-square)](https://github.com/nimbly/Syndicate/actions/workflows/php.yml)
[![Codecov branch](https://img.shields.io/codecov/c/github/nimbly/syndicate/master?style=flat-square)](https://app.codecov.io/github/nimbly/Syndicate)
[![License](https://img.shields.io/github/license/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)

Syndicate is a powerful tool able to both publish and consume messages - ideal for your event driven application or as a job processor. It supports common queues and PubSub integrations with an `Application` layer that can be used to route incoming messages to any handler of your choosing with full dependency injection using a PSR-11 Container instance.

## Requirements

* PHP 8.2+
* php-pcntl (process control)

## Suggested

* PSR-11 Container implementation

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


Is there an implementation you would like to see supported? Let me know in [Github Discussions](https://github.com/nimbly/Syndicate/discussions) or open a Pull Request!

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
	client: new SnsClient(["region" => "us-east-2", "version" => "latest"]),
);

$message = new Message(
	topic: "arn:aws:sns:us-east-2:010393102219:orders",
	payload: \json_encode($order)
);

$publisher->publish($message);
```

## Quick start, consumer

A consumer pulls messages from a known location like a queue or from a PubSub topic and returns those messages.

Select an integration to begin consuming from. In this example, we will be consuming messages from an SQS queue.

```php
$consumer = new Sqs(
	new SqsClient(["region" => "us-east-2", "version" => "latest"])
);

$messages = $consumer->consume($sqs_queue_url, 10);

foreach( $messages as $message ){
	// do your logic...
}
```

### Quick start, Application

The quickstart examples above are sufficient for small singular needs however lacks any real robustness in routing messages of different types to custom handlers.

Typically, you need an application that continuously pulls messages from a known location, and distinctly handles those messages accoridingly.

Syndicate ships with an `Application` instance that can do this for you with full dependency injection support.

```php
$application = new Application(
	consumer: $consumer,
	router: $router,
	deadletter: $deadletter,
	container: $container,
	signals: [SIGINT, SIGTERM, SIGHUP]
)
```

To start consuming messages, call the `listen` method on the application instance.

```php
$application->listen(
	location: "https://sqs.us-east-2.amazonaws.com/123456789012/MyQueue",
	max_messages: 10,
	nack_timeout: 12,
	polling_timeout: 5
);
```

Syndicate will continue to poll for new messages and route them to your handlers. To shutdown the listener, you must send an interrupt signal: SIGINT (ctrl-c), SIGHUP, SIGTERM, etc.

Let's dig deeper into each of the constructor parameters.

### Consumer

The `consumer` parameter is any instance of `Nimbly\Syndicate\ConsumerInterface` - the source where messages should be pulled from.

Example:

```php
$consumer = new Sqs(
	new SqsClient(["region" => "us-east-2", "version" => "latest"])
);
```

### Router

The `router` parameter is any instance of `Nimbly\Syndicate\RouterInterface`. However, there is a default router provided: `Nimbly\Syndicate\Router`. This router relies on your handlers using the `Nimbly\Syndicate\Consume` attribute. Simply add a `#[Consume]` attribute with your routing criteria before your class method on your handlers.

```php
#[Consume(
	topic: "users",
	payload: ["$.event" => "UserCreated"],
)]
public function onUserCreated(Message $message): Response
{
	// Do something with message.
}
```

You can provide multiple values for each property in the `Consume` attribute.

```php
#[Consume(
	topic: ["users", "admins"]
	payload: ["$.event" => "UserCreated", "$.body.role" => ["user", "admin"]],
)]
public function onUserCreated(Message $message): Response
{
	// Do something with message.
}
```

In the above example, *either* the `users` *or* `admins` topic will match *and* the payload JSON paths all must match.

You can also use wildcards when defining matches.

```php
#[Consume(
	topic: "users/*",
	payload: ["$.event" => "Messages/*/User*"],
	headers: ["Origin" => ["*/Syndicate", "Deadletter/*"]]
)]
public function onUserCreated(Message $message): Response
{
	// Do something with message.
}
```

In the above example, any topic that starts with "users/" will match. And the `Origin` header will match as long as it ends with `/Syndicate` *OR* begins with `Deadletter/`.

Finally, you can create a `Nimbly\Syndicate\Router` instance with the class names of your handlers that contain `#[Consume]` attributes.

```php
$router = new Router([
	App\Consumer\Handlers\UsersHandler::class,
	App\Consumer\Handlers\AdminsHandler::class,
]);
```

The default router also supports an optional default handler. This is any `callable` that will be used if no matching routes could be found.

```php
$router = new Router(
	handlers: [
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\AdminsHandler::class,
	],
	default: function(Message $message){
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
			"templates/user_registered"
		);

		if( $result === false ){
			return Response::nack;
		}

		return Response::ack;
	}
}
```

In this example, both the `Message` and the `EmailService` dependecies are injected - assuming the container has the `EmailService` in it.

### Signals

The `signals` parameter is an array of PHP interrupt signal constants (eg, `SIGINT`, `SIGTERM`, etc) that you would like your application to respond to and gracefully shutdown the application. I.e. once all messages in the batch have been processed by your handlers, the application will terminate.

If no signals are caught, any interrupt signal received will force an immediate shutdown, even if in the middle of processing a message which could lead to unintended outcomes.

### LoggerInterface

The `logger` parameter allows you to pass a `Psr\Log\LoggerInterface` instance to the application. Syndicate will use this logger instance to log messages to give you better visibility into your application.

## Handlers

A handler is your code that will receive the event message and process it. The handler can be any `callable` type (closure, function, etc) or a string in the format `Fully\Qualified\NameSpace@methodName` (eg, `App\Consumer\Handlers\UsersHandler@onUserRegistered`).

`Syndicate` will call your handlers with full dependency resolution and injection. This means with a PSR-11 Container instance, your application dependencies can be automatically injected into your handlers.

## Response

After processing a message in your handler, you may return a `Nimbly\Syndicate\Response` enum to explicity declare what should be done with the message.

**NOTE:** If no response value is returned, or the value is not one of `nack` or `deadletter` it is assumed the message should be `ack`ed.

Possible response enum values:

* `Response::ack` - Acknowledge the message
* `Response::nack` - Do not acknowledge the message (the message will be made availble again for processing after a short time)
* `Response::deadletter` - Move the message to a separate deadletter location, provided in the `Application` constructor

Example:

```php
public function onUserRegistered(Message $message, EmailService $email): Response
{
	$payload = \json_decode($message->getBody());

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

Although using the `#[Consume]` attribute is the fastest and easiest way to get your message handlers registered with the application router, you may want to implement your own custom `Message` routing solution. Syndicate provides a `Nimbly\Syndicate\RouterInterface` for you to implement.