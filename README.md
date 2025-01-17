# Syndicate

[![Latest Stable Version](https://img.shields.io/packagist/v/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/nimbly/syndicate/test.yml?style=flat-square)](https://github.com/nimbly/Syndicate/actions/workflows/test.yml)
[![Codecov branch](https://img.shields.io/codecov/c/github/nimbly/syndicate/master?style=flat-square)](https://app.codecov.io/github/nimbly/Syndicate)
[![License](https://img.shields.io/github/license/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)

`Syndicate` is a powerful framework able to both publish and consume messages - ideal for your event driven application or as a job processor. It supports common queues and PubSub integrations with an `Application` layer that can be used to route incoming messages to any handler of your choosing with full dependency injection using a PSR-11 Container instance.

## Requirements

* PHP 8.2+

## Suggested

* ext-pcntl
* PSR-11 Container

## Uses cases

* Publish messages to a queue, pubsub topic, or webhook
* Event message processing
* Background job processing
* General queue message processing

## Supported integrations

### Queues

| Integration    | Publish   | Consume  | Library |
| -------------- | --------- | -------- | ------- |
| Redis          | Y         | Y        | `predis/predis:^2.0` |
| Azure          | Y         | Y        | `microsoft/azure-storage-queue:^1.3` |
| SQS            | Y         | Y        | `aws/aws-sdk-php:^3.336` |
| Beanstalk      | Y         | Y        | `pda/pheanstalk:^5.0` |
| IronMQ         | Y         | Y        | `iron-io/iron_mq:^4.0` |
| RabbitMQ       | Y         | Y        | `php-amqplib/php-amqplib:^3.7` |

### PubSubs

| Integration    | Publish   | Consume  | Library |
| -------------- | --------- | -------- | ------- |
| Redis          | Y         | Y*       | `predis/predis:^2.0` |
| SNS            | Y         | N        | `aws/aws-sdk-php:^3.336` |
| MQTT           | Y         | Y*       | `php-mqtt/client:^1.1` |
| Google         | Y         | Y        | `google/cloud-pubsub:^2.0` |
| Webhook        | Y         | N        | n/a |

**NOTE:** Consumers denoted with **\*** indicate they do not support `ack`ing, `nack`ing, or `deadletter`ing messages. Additionally, the `predis/predis` library currently does not play well with interrupts and gracefully stopping its internal pubsub loop. If using this integration, you should set the `signals` option to an empty array. See the **Consumer** section below for more details.

Is there an integration you would like to see supported? Let us know in [Github Discussions](https://github.com/nimbly/Syndicate/discussions) or open a Pull Request!

Alternatively, you can implement your own consumers and publishers by adhering to the `Nimbly\Syndicate\ConsumerInterface` and/or `Nimbly\Syndicate\PublisherInterface` interfaces.

## Installation

```bash
composer require nimbly/syndicate
```

## Publisher: Quick Start

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

## Application: Quick Start

Create a consumer instance by selecting your integration.

```php
$consumer = new Sqs(
	new SqsClient([
		"region" => "us-west-2",
		"version" => "latest"
	])
);
```

Create an `Application` instance with your consumer and a `Router` instance with the class names of where your handlers are. The classes you use for your handlers should have methods tagged with the `#[Consume]` attribute. (See **Handlers** and **Consume Attribute** sections for more details.)

```php
$application = new Application(
	consumer: $consumer,
	router: new Router([
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\OrdersHandler::class,
	]),
);
```

To start consuming messages, call the `listen` method on the application instance with the topic name, queue name, or queue URL as the `location`.

```php
$application->listen(
	location: "https://sqs.us-west-2.amazonaws.com/123456789012/MyQueue",
);
```

## Application: In Depth

The quick start above only scratches the surface of what `Syndicate` can do. Let's look at more detailed examples of all its options and features.

```php
$application = new Application(
	consumer: $consumer,
	router: new Router([
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\OrdersHandler::class,
	]),
	deadletter: new DeadletterPublisher(
		$consumer,
		"https://sqs.us-west-2.amazonaws.com/123456789012/deadletter",
	),
	container: $container,
	logger: $logger,
	signals: [SIGINT, SIGTERM, SIGHUP]
)
```

### Consumer

The `consumer` parameter is any instance of `Nimbly\Syndicate\ConsumerInterface` or `Nimbly\Syndicate\LoopConsumerInterface` - the source where messages should be pulled from.

```php
$consumer = new Sqs(
	new SqsClient([
		"region" => "us-west-2",
		"version" => "latest"
	])
);
```

#### A note on the LoopConsumerInterface
`LoopConsumerInterface` integrations behave a little differently than the other integrations in that the libraries that back them already have their own looping solution for consuming messages.

These integrations do not support `ack`ing or `nack`ing of messages due to the nature of pubsub. `deadletter`ing messages with these integrations is not currently supported natively by `Syndicate`. Any return value from handlers will be ignored by these integrations.

Setting up a deadletter for these integrations *could* be achieved by defining a default handler option in the `Router` that publishes to another location.

```php
// Create a Redis queue publisher instance as our deadletter.
$deadletter = new Redis(new Client);

$application = new Application(
	consumer: new Mqtt(new MqttClient("localhost")),
	router: new Router(
		handlers: [
			App\Consumer\Handlers\UsersHandler::class
		],
		default: function(Message $message) use ($deadletter): void {
			$deadletter->publish(
				new Message(
					topic: "deadletter",

					// Use the original topic and original payload.
					payload: \json_encode([
						"topic" => $message->getTopic(),
						"payload" => \json_decode($message->getPayload())
					])
				)
			);
		}
	)
);
```

### Router

The `router` parameter is an instance of `Nimbly\Syndicate\Router`. This router relies on your handlers using the `Nimbly\Syndicate\Consume` attribute. Simply add a `#[Consume]` attribute with your routing criteria before your class methods on your handlers (please see **Handlers** and **Consume Attribute** sections for more details). Finally, pass these class names off to the `Router` instance.

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

The `deadletter` parameter allows you to define a deadletter location: a place to put messages that cannot be routed or processed for whatever reason. The `deadletter` is simply a `PublisherInterface` instance - however, a helper is provided with the `DeadletterPublisher` class.

```php
$redis = new Nimbly\Syndicate\Queue\Redis(new Client);

$deadletter = new DeadletterPublisher(
	$redis,
	"deadletter"
);
```

In this example, we would like to use a Redis queue for our deadletters and to push them into the `deadletter` queue.

The `deadletter` implementation is used any time a message could not be routed and no default handler was provided *or* if you explicitly return `Response::deadletter` from your message handler.

### Container

The `container` parameter allows you to pass along a PSR-11 Container instance to be used in autowiring and dependency injection when calling your message handlers.

**NOTE:** The `Nimbly\Syndicate\Message` instance can *always* be resolved with or without a conatiner.

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

### Logger

The `logger` parameter allows you to pass a `Psr\Log\LoggerInterface` instance to the application. `Syndicate` will use this logger instance to log messages to give you better visibility into your application.

### Signals

The `signals` parameter is an array of PHP interrupt signal constants (eg, `SIGINT`, `SIGTERM`, etc) that you would like your application to respond to and gracefully shutdown the application. I.e. once all messages in flight have been processed by your handlers, the application will terminate. It defaults to `[SIGINT, SIGTERM]` which are common interrupts for both command line (Ctrl-C) and container orchestration systems like Kubernetes or ECS.

If no signals are defined, any interrupt signal received will force an immediate shutdown, even if in the middle of processing a message. This could lead to unintended outcomes like lost messages or messages that were only partially processed by your handlers.

**NOTE:** Graceful shutdown via interrupt signals *requires* the `ext-pcntl` PHP extension and is only available on Unix-like systems (Linux & Mac OS).

## Listening

The `listen` method will start the polling process for new messages and route them to your handlers. To shutdown the listener, you must send an interrupt signal that was defined in the `Application` constructor, typically `SIGINT` (Ctrl-C) or `SIGTERM`.

```php
$application->listen(
	location: "https://sqs.us-west-2.amazonaws.com/123456789012/MyQueue",
	max_messages: 10,
	nack_timeout: 12,
	polling_timeout: 5,
	deadletter_options: ["option" => "value"]
);
```

For consumers that implement the `LoopConsumerInterface` (curently `PubSub\Redis` and `PubSub\Mqtt`), you can pass in an array of `location` strings representing `topics` to subscribe to or a comma seperated list of topic names.

```php
$application->listen(
	location: ["users", "orders"],
);
```

### Location

The `location` parameter is the topic name, queue name, or queue URL you will be listening on. This parameter value is dependent on which consumer implementation you are using.

### Max Messages

The `max_messages` parameter defines how many messages should be pulled off at a single time. Some implementations only allow a single message at a time, regardless of what value you use here.

### Nack Timeout

The `nack_timeout` parameter defines how long (in minutes) the message should be held before it can be pulled again when a `Response::nack` is returned by your handler. Some implementations do not support modifying the message visibility timeout and will ignore this value entirely.

### Polling Timeout

The `polling_timeout` parameter defines how long (in seconds) the consumer implementation should block waiting for messages before disconnecting and trying again.

### Deadletter Options
The `deadletter_options` parameter is a set of options that will be passed to the deadletter publisher. These options are dependent on the implementation being used.

## Handlers

A handler is your code that will receive the  `Nimbly\Syndicate\Message` instance and process it. The handler can be any `callable` type but typically is a class method.

`Syndicate` will call your handlers with full dependency resolution and injection as long as a PSR-11 Container instance was provided. Both the constructor and the method to be called will have dependencies automatically resolved and injected for you.

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

### Message

`Syndicate` will pass a `Nimbly\Syndicate\Message` instance to your handler. This `Message` instance contains the topic, payload, headers, and attributes of the message that was consumed. The payload is returned exactly as it was consumed: no parsing of the data is done.

**NOTE:** Not all consumers support headers or attributes.

```php
public function onUserRegistered(Message $message, EmailService $email): Response
{
	// Get the topic, queue name, or queue URL the message came from
	$topic = $message->getTopic();

	// JSON decode the message payload
	$payload = \json_decode($message->getPayload());

	// Get all headers of the message
	$headers = $message->getHeaders();

	// Get all attributes of the message
	$attributes = $message->getAttributes();
}
```

### Response

After processing a message in your handler, you may (and should) return a `Nimbly\Syndicate\Response` enum to explicity declare what should be done with the message.

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

Although using the `#[Consume]` attribute is the fastest and easiest way to get your message handlers registered with the application router, you may want to implement your own custom  routing solution. `Syndicate` provides a `Nimbly\Syndicate\RouterInterface` for you to implement.

## Custom publishers and consumers

If you find that `Syndicate` does not support a particular publisher or consumer, we'd love to see a [Github Issues](https://github.com/nimbly/Syndicate/issues) opened or a message posted in [Github Discussions](https://github.com/nimbly/Syndicate/discussions).

Alternatively, you can create your own implementation using the `Nimbly\Syndicate\PublisherInterface` and/or the `Nimbly\Syndicate\ConsumerInterface`.

If you feel like sharing your implementations, we encourage you opening up a PR!