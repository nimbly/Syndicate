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


Is there an implementation you would like to see supported? Let me know in [Github Discussions](https://github.com/nimbly/Syndicate/discussions) or open a Pull Request!

Alternatively, you can implement your own consumers and publishers by adhering to the `ConsumerInterface` and/or `PublisherInterface` interfaces.

## Installation

```bash
composer require nimbly/syndicate
```

## Quick start, publisher

A publisher sends (aka publishes) messages to a known location like a queue or to a PubSub topic.

Select an integration you would like to publish messages to. In this example, we will be publishing messags to an SNS topic.

```php
$publisher = new Sns(
	new SnsClient(["region" => "us-east-2", "version" => "latest"])
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

## Application

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
	location: "aws_queue_url",
	max_messages: 10,
	nack_timeout: 12,
	polling_timeout: 5
);
```

Syndicate will continue to poll for new messages and route them to your handlers. To shutdown the listener, you must send an interrupt signal: SIGINT (ctrl-c), SIGHUP, SIGTERM, etc.

### Consumer

The consumer parameter is any instance of `ConsumerInterface` - the source where messages should be pulled from.

Example:

```php
$consumer = new Sqs(
	new SqsClient(["region" => "us-east-2", "version" => "latest"])
);
```

### Router

In order to route incoming messages to a particular handler, you must define an implementation for `RouterInterface`.

Because event messages are unique per implementor and no standards exist, you must provide a way for `Syndicate` to know how to route each message. This could be based on the message topic, it could be based on a particular value in the message payload, or any myriad other ways.

This interface provides a single `resolve` method that accepts a `Nimbly\Syndicate\Message` instance and returns a handler on match. The handler can be any `callable` or a string in the format `Fully\Qualified\ClassName@method` (eg, `App\Consumer\Handlers\UsersHandler@onUserRegistered`). `Syndicate` will automatically build and call this handler for you using dependency injection. If no matching handler could be found, you should return `null`.

#### Example

Suppose our event messages follow a company-wide standard. The message contains a unique ID, an origin from where the event was published, a timestamp of when the event was published, and a `name` property representing the name of the event.

The message also contains a body that is unique to each kind of event. In this example, user account details for the `UserRegistered` event.

```json
{
 	"id": "305d3112-b5ac-4643-921d-22c671b2b5b1",
 	"name": "UserRegistered",
 	"origin": "user_service.prod.company.com",
 	"published_at": "2024-05-12T13:38:02Z",
 	"body": {
 		"id": "598f38b6-39e1-42ee-a085-e3591a77d6b4",
 		"name": "John Doe",
 		"email": "john@example.com"
 	}
}
```

We would like to route messages to specific handlers based on the `name` field in the message: the name of the event.

We might create a router that looks like this:

```php
class Router implements RouterInterface
{
	public function __construct(
		protected array $routes,
		protected callable $default
	)
	{
	}

	public function resolve(Message $message): string|callable|null
	{
		$payload = \json_decode($message->getPayload());

		foreach( $this->routes as $key => $handler ){
			if( $payload->name === $key ){
				return $handler;
			}
		}

		return $this->default;
	}
}
```

When we instantiate our new router class, it might look something like this:

```php
$router = new Router(
	routes: [
		"UserRegistered" => "App\\Consumer\\Handlers\\UsersHandler@onUserRegistered"
	],
	default: function(): Response {
		\error_log("No handler could be matched for this message, sending to deadletter queue.");
		return Response::deadletter;
	}
)
```

### Deadletter

Syndicate can push messages to a deadletter queue if you would like.

A deadletter is simply a `PublisherInterface` instance. However, a helper is provided with the `DeadletterPublisher` class.

```php
$redis = new Nimbly\Syndicate\Queue\Redis(new Client);

$deadletter = new DeadletterPublisher(
	$redis,
	"foo_service_deadletter"
);
```

In this example, we would like to use a Redis queue for our deadletters and to push them into the `foo_service_deadletter` queue.

### Container

As mentioned earlier, `Syndicate` is capable of full dependency resolution and injection when calling your handlers.

To take full advantage of that, you may also provide a PSR-11 Container instance so that your domain dependencies can be resolved.

```php
class UsersHandler
{
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

In this example, both the `Message` and the `EmailService` dependecies are injected - assuming the container has the `EmailService` in it. The `Nimbly\Syndicate\Message` instance can always be resolve without the need of a conatiner.

### Signals

The signals option is an array of PHP interrupt signal constants (eg, `SIGINT`, `SIGTERM`, etc) that you would like your application to respond to and gracefully shutdown the application. I.e. once all messages in the batch have been processed by your handlers, the application will terminate.

If no signals are caught, any interrupt signal received will force an immediate shutdown, even if in the middle of processing a message which could lead to unintended outcomes.

## Handlers

A handler is your code that will receive the event message and process it. The handler can be any `callable` type (closure, function, etc) or a string in the format `Fully\Qualified\NameSpace@methodName` (eg, `App\Consumer\Handlers\UsersHandler@onUserRegistered`).

`Syndicate` will call your handlers with full dependency resolution and injection. This means with a PSR-11 Container instance, your application dependencies can be automatically injected into your handlers.

## Response

After processing a message in your handler, you may return a `Nimbly\Syndicate\Response` enum to explicity declare what should be done with the message.

**NOTE:** If no response value is returned, it is assumed the message should be `ack`ed.

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