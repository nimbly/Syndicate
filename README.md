# Syndicate

[![Latest Stable Version](https://img.shields.io/packagist/v/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/nimbly/syndicate/test.yml?style=flat-square)](https://github.com/nimbly/Syndicate/actions/workflows/test.yml)
[![Codecov branch](https://img.shields.io/codecov/c/github/nimbly/syndicate/master?style=flat-square)](https://app.codecov.io/github/nimbly/Syndicate)
[![License](https://img.shields.io/github/license/nimbly/Syndicate.svg?style=flat-square)](https://packagist.org/packages/nimbly/Syndicate)

Syndicate is a powerful framework able to both publish and consume messages - ideal for your event driven application or as a job processor. It supports common queues and PubSub integrations with an `Application` layer that can be used to route incoming messages to any handler of your choosing with full dependency injection using a PSR-11 Container instance.

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

| Adapter        | Publish   | Consume  | Library |
| -------------- | --------- | -------- | ------- |
| [Azure](/ADAPTERS.md#azure)          | Y         | Y        | `microsoft/azure-storage-queue` |
| [Beanstalk](/ADAPTERS.md#beanstalk)      | Y         | Y        | `pda/pheanstalk` |
| [Gearman](/ADAPTERS.md#gearman)        | Y         | Y*       | `ext-gearman` |
| [Google](/ADAPTERS.md#google)         | Y         | Y        | `google/cloud-pubsub` |
| [IronMQ](/ADAPTERS.md#ironmq)         | Y         | Y        | `iron-io/iron_mq` |
| [Mercure](/ADAPTERS.md#mercure)        | Y         | N        | Any `psr/http-client` implementation |
| [MockSubscriber](/ADAPTERS.md#mock) | Y         | Y        | - |
| [MockQueue](/ADAPTERS.md#mock)           | Y         | Y        | - |
| [MQTT](/ADAPTERS.md#mqtt)           | Y         | Y*       | `php-mqtt/client` |
| [NullPublisher](/ADAPTERS.md#nullpublisher) | Y         | N        | - |
| [Outbox](/ADAPTERS.md#outbox)         | Y         | N        | `ext-pdo` |
| [RabbitMQ](/ADAPTERS.md#rabbitmq)       | Y         | Y        | `php-amqplib/php-amqplib` |
| [Redis](/ADAPTERS.md#redis-queue)          | Y         | Y        | `predis/predis` |
| [RedisPubsub](/ADAPTERS.md#redis-pubsub)    | Y         | Y*       | `predis/predis` |
| [Segment](/ADAPTERS.md#segment)    | Y         | N       | `segmentio/analytics-php` |
| [SNS](/ADAPTERS.md#sns)            | Y         | N        | `aws/aws-sdk-php` |
| [SQS](/ADAPTERS.md#sqs)            | Y         | Y        | `aws/aws-sdk-php` |
| [Webhook](/ADAPTERS.md#webhook)        | Y         | N        | Any `psr/http-client` implementation |

For detailed information on each adapter, please read the [ADAPTERS.md](/ADAPTERS.md) documentation.

**NOTE:** Consumers denoted with **\*** indicate subscriber based adapters and do not support `ack`ing or `nack`ing due to the nature of pubsub. Additionally, the `predis/predis` library currently does not play well with interrupts and gracefully stopping its internal pubsub loop. If using this adapter, you should set the `signals` option to an empty array. See the [**Subscribers**](#subscribers) section below for more details.

Is there an integration you would like to see supported? Let us know in [Github Discussions](https://github.com/nimbly/Syndicate/discussions) or open a Pull Request!

Alternatively, you can implement your own consumers, subscribers, and publishers by adhering to the `Nimbly\Syndicate\ConsumerInterface`, `Nimbly\Syndicate\SubscriberInterface`, and `Nimbly\Syndicate\PublisherInterface` interfaces.

## Installation

```bash
composer require nimbly/syndicate
```

## Table of contents
* [Quick Start](#quick-start)
	* [Publisher](#publisher-quick-start)
	* [Application](#application-quick-start)
* [Publishers](#publishers)
	* [Messages](#messages)
	* [Filters](#filters)
		* [RedirectFilter](#redirectfilter)
		* [ValidatorFilter](#validatorfilter)
* [Consumers](#consumers)
	* [Subscribers](#subscribers)
* [Routing](#routing)
* [Handlers](#handlers)
	* [Consume Attribute](#consume-attribute)
	* [Response](#response)
* [Application](#application)
	* [Container](#container)
	* [Deadletter](#deadletter)
	* [Logging](#logging)
	* [Middleware](#middleware)
		* [ParseJsonMessage](#parsejsonmessage)
		* [ValidateMessage](#validatemessage)
		* [DeadletterMessage](#deadlettermessage)
	* [Signals](#signals)
	* [Starting the application](#starting-the-application)
* [Validators](#validators)
	* [JSON Schema](#json-schema)

## Quick Start

### Publisher Quick Start

A publisher sends (aka publishes) messages to a known location like a queue or to a PubSub topic.

Select an adapter you would like to publish messages to. In this example, we will be publishing messages to an SNS topic.

```php
$publisher = new Sns(
	client: new SnsClient(["region" => "us-west-2", "version" => "latest"])
);

$message = new Message(
	topic: "arn:aws:sns:us-west-2:123456789012:orders",
	payload: \json_encode($order)
);

$publisher->publish($message);
```

You can also add any number of publishing filters for things like validating your messages against a JSON schema or redirecting messages to another topic. See [**Filters**](#filters) section for more information.

### Application Quick Start

Create a consumer instance by selecting your adapter.

```php
$consumer = new Sqs(
	new SqsClient([
		"region" => "us-west-2",
		"version" => "latest"
	])
);
```

Create an `Application` instance with your consumer and a `Router` instance with the class names of where your handlers are. The classes you use for your handlers should have methods tagged with the `#[Consume]` attribute. See [**Handlers**](#handlers) and [**Consume Attribute**](#consume-attribute) sections for more details.

```php
$application = new Application(
	consumer: $consumer,
	router: new Router([
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\OrdersHandler::class
	])
);
```

To start consuming messages, call the `listen` method on the application instance with the topic name, queue name, or queue URL as the `location`.

```php
$application->listen(
	location: "https://sqs.us-west-2.amazonaws.com/123456789012/MyQueue"
);
```

Your application should now start to consume messages off the source and route them to your handlers for processing.

To stop processing messages, press **Ctrl-c** to initiate a graceful shutdown.

## Publishers

A publisher is an instance that sends (aka publishes) messages to a known location. The message contains all information the publisher needs to know including the topic, name, or destination URL of the message, the payload of the message, and if the integration supports it, headers and attributes.

Once the message has been published, the integration chosen *may* return an acknowledgement like an ID or receipt of some sort.

Please refer to the [**Supported integrations**](#supported-integrations) for detailed information on what publisher integrations are available.

```php
$publisher = new Sns(
	new SnsClient($aws_config)
);

$receipt = $publisher->publish($message);
```

### Messages

A publisher must have a `Nimbly\Syndicate\Message` instance to send.

The `Message` instance contains the `topic` and `payload` of the message you would like to send.

```php
$message = new Message(topic: "users", payload: \json_encode($user));
```

If the adapter supports it, the `Message` instance can also contain `headers` and `attributes`. These are simple key/value pair maps that are highly dependent on the adapter chosen. Please refer to the vendor's documentation to see if these are supported and what possible values it may contain.

```php
$message = new Message(
	topic: "users",
	payload: \json_encode($user),
	headers: ["Header1" => "Value1"],
	attributes: ["id" => (string) Uuid::uuid4(), "priority" => "high"]
);

$publisher->publish($message);
```

### Filters

Namespace: `Nimbly\Syndicate\Filter`

Filters allow you to modify or interact with a message *before* it gets published. These filters will wrap around your actual publisher to provide additional functionality. You can stack as many filters on top of each other as you would like.

```php
$publisher = new ValidateMessage(
	new JsonSchemaValidator([
		"users" => $schema
	]),
	new Sns(
		new SnsClient($aws_config)
	)
);
```

#### RedirectFilter

Typically, a `Message` will be published to its defined topic. The `RedirectFilter` allows you to over ride or redirect that `Message` to a completely different topic.

```php
$publisher = new RedirectMessage(
	new Sqs(
		new SqsClient($aws_config)
	),
	"https://sqs.us-west-2.amazonaws.com/123456789012/deadletter"
);

/**
 * Despite this message being intended for the "fruits" topic, it
 * will actually be published to https://sqs.us-west-2.amazonaws.com/123456789012/deadletter.
 */
$publisher->publish(new Message("fruits", "banana"));
```

#### ValidatorFilter

The `ValidatorFilter` will validate all messages *before* being published. If the message does not validate, a `MessageValidationException` is thrown.

```php
$publisher = new ValidatorFilter(
	new JsonSchemaValidator(["fruits" => $fruits_schema]),
	new Sns(new SnsClient($aws_config))
);

$publisher->publish($message);
```

## Consumers

Consumers are instances that pull (or consume) messages from a known location. These locations can be standard queue, a pubsub topic, or anything else that has messages waiting to be consumed.

Please refer to the [**Supported integrations**](#supported-integrations) for detailed information on what consumer integrations are available.

```php
$consumer = new Sqs(
	new SqsClient($aws_config)
);
```

### Subscribers

A variation of Consumers, Subscriber adapters use a *slightly* different technique to get their messages consumed and are *typically* pubsub. However, the `Application` can still use them to route messages to your handlers. One noticeable difference is that when starting the `Application`, you can provide an array of topics to consume from, rather than a single queue URL or name.

**NOTE:** These adapters do not support `ack`ing or `nack`ing of messages due to the nature of pubsub. `deadletter`ing from handlers is possible by adding the `Nimbly\Syndicate\Middleware\DeadletterMessage` middleware and returning `Response::deadletter` from your handlers. Any other return value from your handlers will be completely ignored by these adapters.

## Routing

In order to dispatch consumed messages to the matching handler, a `Nimbly\Syndicate\Router\Router` is needed. This router relies on your handlers using the `Nimbly\Syndicate\Router\Consume` attribute to define routing criteria. Simply add a `#[Consume]` attribute with your routing criteria before your class methods on your handlers. Please see [**Handlers**](#handlers) and [**Consume Attribute**](#consume-attribute) sections for more details.

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
		App\Consumer\Handlers\OrdersHandler::class
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

## Handlers

A handler is your code that will receive the `Nimbly\Syndicate\Message` instance and process it. The handler can be any `callable` type but typically is a class method.

The `Message` instance contains the topic, payload, headers, and attributes of the message that was consumed. The payload is returned exactly as it was consumed: no parsing of the data is done. However, you can apply the `ParseJsonMessage` middleware to provide the parsed message via the `Message::getParsedPayload` method.

```php
public function onUserRegistered(Message $message, EmailService $email): Response
{
	// Get the topic, queue name, or queue URL the message came from
	$topic = $message->getTopic();

	// JSON decode the message payload
	$payload = \json_decode($message->getPayload());

	// Get the pre-parsed payload, provided by the ParseJsonMessage middleware
	$parsed_payload = $message->getParsedPayload();

	// Get all headers of the message
	$headers = $message->getHeaders();

	// Get all attributes of the message
	$attributes = $message->getAttributes();
}
```

Syndicate will call your handlers with *full* dependency resolution and injection as long as a PSR-11 Container instance was provided to the `Application` instance. Both the constructor and the method to be called will have dependencies automatically resolved and injected for you.

**NOTE:** The `Nimbly\Syndicate\Message` instance can *always* be resolved with or without a conatiner.

```php
namespace App\Consumer\Handlers;

use App\Services\EmailService;
use Nimbly\Syndicate\Router\Consume;
use Nimbly\Syndicate\Message;

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
		$this->logger->debug("Received UserCreated message.");

		$payload = \json_decode($message->getPayload());

		$receipt_id = $email->send(
			$payload->user_name,
			$payload->user_email,
			"templates/registration.tpl"
		);

		return Response::ack;
	}
}
```

### Consume Attribute

The `Nimbly\Syndicate\Router\Consume` attribute allows you to add message routing criteria to your handlers. The criteria supported are:

* `topic` The topic name or an array of names.
* `payload` An array of key/value pair of JSON Path statements to a string or array of strings to match.
* `headers` An array of key/value pair of header names to a string or array of strings to match.
* `attributes` An array of key/value pair of attribute names to a string or array of strings to match.

You can have as many or as few routing criteria as you like. You may also use an **\*** (asterisk) as a wildcard for matching. Each type of criteria you add is **AND**ed together.

**NOTE:** In order to use the `payload` filter, your message content **must** be in JSON.

#### Examples

Here is an example where the topic must match exactly `users` **AND** the message body must have a `type` property that is exactly `UserCreated` **AND** the `body.role` property is either `user` **OR** `admin`.

```php
#[Consume(
	topic: "users",
	payload: ["$.type" => "UserCreated", "$.body.role" => ["user", "admin"]]
)]
```

Here is an example where the topic must start with `users/` **AND** the payload `type` property either starts with `User` **OR** starts with `Admin` **AND** the payload `body.role` property is either `user` **OR** `admin`.

```php
#[Consume(
	topic: ["users/*"],
	payload: ["$.type" => ["User*", "Admin*"], "$.body.role" => ["user", "admin"]]
)]
```

In this example, the `Origin` header will match as long as it ends with `/Syndicate` **OR** begins with `Deadletter/`.

```php
#[Consume(
	headers: ["Origin" => ["*/Syndicate", "Deadletter/*"]]
)]
```

### Response

After processing a message in your handler, you may (and should) return a `Nimbly\Syndicate\Response` enum to explicity declare what should be done with the message.

Possible response enum values:

* `Response::ack` - Acknowledge the message (removes the message from the source)
* `Response::nack` - Do not acknowledge the message (the message will be made availble again for processing after a short time, also known as releasing the message)
* `Response::deadletter` - Move the message to a separate deadletter location, provided in the `Application` constructor. If you are using a `SubscriberInterface` instance, be sure to include the `DeadletterMiddleware`.

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

## Application

The application quick start above only scratched the surface of what Syndicate can do. Let's look at more detailed examples of all its options and features.

The Application is where all the concepts above are pooled together to create one seamless experience.

```php
$application = new Application(
	consumer: $consumer,
	router: new Router([
		App\Consumer\Handlers\UsersHandler::class,
		App\Consumer\Handlers\OrdersHandler::class
	]),
	deadletter: new RedirectFilter(
		$consumer,
		"https://sqs.us-west-2.amazonaws.com/123456789012/deadletter"
	),
	container: $container,
	logger: $logger,
	middleware: [
		new ValidateMessages(
			new JsonSchemaValidator(["topic" => $schema])
		)
	],
	signals: [SIGINT, SIGTERM, SIGHUP]
);
```

### Deadletter

The `deadletter` parameter allows you to define a deadletter location: a place to put messages that cannot be routed or processed for whatever reason. The `deadletter` is simply a `PublisherInterface` instance - however, you will almost certainly need the `RedirectFilter` applied to send to a different topic.

```php
// Use Redis queue as our main consumer.
$redis = new Nimbly\Syndicate\Adapter\Redis(new Client);

// Redirect all messages to the "deadletter" queue in Redis.
$deadletter = new RedirectFilter($redis, "deadletter");
```

In this example, we would like to use a Redis queue for our deadletters and to push them into the `deadletter` queue.

The `deadletter` implementation is used any time a message could not be routed and no default handler was provided *or* if you explicitly return `Response::deadletter` from your message handler. See [**Response**](#response) section for more information.

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

The `logger` parameter allows you to pass a `Psr\Log\LoggerInterface` instance to the application. Syndicate will use this logger instance to log messages to give you better visibility into your application.

### Middleware

The `middleware` parameter allows you to pass an array of middleware to be applied globally to all incoming messages and outgoing responses from your handler. Middleware are processed in the order you have defined in the array.

All middleware should implement `Nimbly\Syndicate\Middleware\MiddlewareInterface`. The middleware chain supports dual pass: both the incoming consumed `Message` instance and whatever value the handler returned.

Below are some prebuilt middleware that you may add to your application.

#### ParseJsonMessage

This middleware will JSON decode your Message payload and make the result available on the message via `getParsedBody` method.

If the payload cannot be JSON decoded, the message will attempted to be deadlettered.

```php
public function onUserCreated(Message $message): Response
{
	$payload = $message->getParsedPayload();

	// Do something with message...
}
```

#### ValidateMessage

This middleware will validate *incoming* consumed messages. You must supply the `ValidatorInterface` instance to use for validating messages.

If validation fails, the message will attempted to be deadlettered.

 ```php
$middleware = new ValidateMessage(
	new JsonSchemaValidator([
		"fruits" => \file_get_contents(__DIR__ . "/schemas/fruits.json"),
		"veggies" => \file_get_contents(__DIR__ . "/schemas/veggies.json")
	])
);
```

#### DeadletterMessage

This middleware is a shim to add deadletter support for `SubscriberInterface` based adapters (typically pubsub integrations.) With this middleware active, you can return `Response::deadletter` from your handlers and this middleware will publish them to your deadletter for you.

```php
$middleware = new DeadletterMessage(
	new RedirectFilter($publisher, "deadletter")
);
```

#### Custom Middleware

To add your own custom middleware, just implement `Nimbly\Syndicate\Middleware\MiddlewareInterface`.


```php
class MyMiddleware implements MiddlewareInterface
{
	public function handle(Message $message, callable $next): mixed
	{
		Log::debug(
			"Received message",
			["topic" => $message->getTopic(), "payload" => $message->getPayload()]
		);

		$response = $next($message);

		if( $response === Response::deadletter ){
			Log::warning(
				"Deadletter message",
				["topic" => $message->getTopic(), "payload" => $message->getPayload()]
			);
		}

		return $response;
	}
}
```

#### Signals

The `signals` parameter is an array of PHP interrupt signal constants (eg, `SIGINT`, `SIGTERM`, etc) that you would like your application to respond to and gracefully shutdown the application. I.e. once all messages in flight have been processed by your handlers, the application will terminate. It defaults to `[SIGINT, SIGTERM]` which are common interrupts for both command line (Ctrl-C) and container orchestration systems like Kubernetes or ECS.

If no signals are defined, any interrupt signal received will force an immediate shutdown, even if in the middle of processing a message. This could lead to unintended outcomes like lost messages or messages that were only partially processed by your handlers.

**NOTE:** Graceful shutdown via interrupt signals *requires* the `ext-pcntl` PHP extension and is only available on Unix-like systems (Linux & Mac OS).

### Starting the application

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

#### Location

The `location` parameter is the topic name, queue name, or queue URL you will be listening on. This parameter value is dependent on which consumer adapter you are using.

For consumers that implement the `SubscriberInterface` (curently `PubSub\Redis`, `PubSub\Gearman`, and `PubSub\Mqtt`), you can pass in an array of `location` strings representing `topics` to subscribe to or a comma seperated list of topic names.

```php
$application->listen(
	location: ["users", "orders", "returns"]
);
```

```php
$application->listen(
	location: "users, orders, returns"
);
```

#### Max Messages

The `max_messages` parameter defines how many messages should be pulled off at a single time. Some implementations only allow a single message at a time, regardless of what value you use here.

#### Nack Timeout

The `nack_timeout` parameter defines how long (in minutes) the message should be held before it can be pulled again when a `Response::nack` is returned by your handler. Some implementations do not support modifying the message visibility timeout and will ignore this value entirely.

#### Polling Timeout

The `polling_timeout` parameter defines how long (in seconds) the consumer implementation should block waiting for messages before disconnecting and trying again.

#### Deadletter Options
The `deadletter_options` parameter is a set of options that will be passed to the deadletter publisher. These options are dependent on the implementation being used.

## Validators

A good practice is to validate your messages before publishing, before consuming them, or at least within your unit tests. Syndicate offers a `ValidatorFilter` filter that can assist in this: each message will be validated against your chosen validator before being published. Currently, only a `JsonSchemaValidator` is available.

If the message fails validation, a `MessageValidationException` will be thrown.

### JSON Schema

Syndicate ships with a `JsonSchemaValidator` that can be used to validate messages against a JSON schema. This validator can be used with the built-in `ValidateMessage` middleware or the `ValidatorFilter` publisher filter.

```php
$publisher = new ValidatorFilter(
	new JsonSchemaValidator([
		"fruits" => \file_get_contents(__DIR__ . "/schemas/fruits.json"),
		"veggies" => \file_get_contents(__DIR__ . "/schemas/veggies.json")
	]),
	new Mqtt(new MqttClient("localhost"))
);

$publisher->publish(new Message("veggies", \json_encode($payload)));
```

In the example above, the `Mqtt` publisher will be used to publish messages and the `Message` instance being published will be validated against the `veggies` JSON schema.

## Custom router

Although using the `#[Consume]` attribute is the fastest and easiest way to get your message handlers registered with the application router, you may want to implement your own custom routing solution. Syndicate provides a `Nimbly\Syndicate\RouterInterface` for you to implement.

## Custom publishers, consumers, and subscribers

If you find that Syndicate does not support a particular publisher or consumer, we'd love to see a [Github Issues](https://github.com/nimbly/Syndicate/issues) opened or a message posted in [Github Discussions](https://github.com/nimbly/Syndicate/discussions).

Alternatively, you can create your own implementation using the `Nimbly\Syndicate\PublisherInterface`, `Nimbly\Syndicate\ConsumerInterface`, or `SubscriberInterface`.

If you feel like sharing your implementations, we encourage you opening up a PR!