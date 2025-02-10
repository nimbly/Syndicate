# Adapters

The following is a list of currently supported adapters and any particular notes, special options, and message attributes that can be used with them.

## Azure

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Azure` | Y       | Y       | `microsoft/azure-storage-queue` |

### Install

```bash
composer require microsoft/azure-storage-queue
```

**NOTE:** Azure has stopped development and maintenance of this library.

## Beanstalk

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Beanstalk` | Y       | Y       | `pda/pheanstalk` |

### Install

```bash
composer require pda/pheanstalk
```

### Message attributes

The following message attributes are supported when publishing messages:

* `priority` (integer, optional, defaults to `Pheanstalk::DEFAULT_PRIORITY`) The priority of the message.
* `delay` (integer, optional, defaults to `Pheanstalk::DEFAULT_DELAY`) The delay in seconds before message becomes available.
* `time_to_release` (integer, optional, defaults to `Pheanstalk::DEFAULT_TTR`) The amount of time (in seconds) a handler has to process a message before it is automatically released.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["priority" => 0, "delay" => 30, "time_to_release" => 120]
	)
);
```

## Gearman

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Gearman` | Y       | Y       | `ext-gearman` |

**NOTE:** Only background jobs are supported.

### Install

```bash
sudo apt-get install php-gearman
```

Or you can install directly from PEAR/PECL.

```bash
sudo pecl install gearman
```

### Message attributes

The following message attributes are supported when publishing a message:

* `priority` (string, optional, defaults to `normal`) Publish the message with a particular priority. Possible values are `low`, `normal`, or `high`.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["priority" => "high"]
	)
);
```

## IronMQ

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Iron` | Y       | Y       | `iron-io/iron_mq` |

### Install

```bash
composer require iron-io/iron_mq
```

### Message attributes

The following message attributes are supported when publishing messages:

* `delay` (integer, optional, default `0`) Amount of time (in seconds) before message becomes available for consuming.
* `timeout` (integer, optional, default `60`) Amount of time (in seconds) a reserved message is automatically released.
* `expires_in` (integer, optional, default `604800`) Amount of time (in seconds) a message will be auto-deleted.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["delay" => 30, "timeout" => 120, "expires_in" => 86400]
	)
);
```

## Google

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Google` | Y       | Y       | `google/cloud-pubsub` |

### Install

To use this adapter, you must install the `google/cloud-pubsub` library.

```bash
composer require google/cloud-pubsub
```

## Mercure

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Mercure` | Y       | N       | `psr/http-client` |

If no `psr/http-client` implementation is given, this adapter will fall back to using `nimbly/shuttle` which is bundled with Syndicate.

### Message attributes

The following message attributes are supported when publishing a message:

* `id` (string, optional) A unique ID for this message. If none provided, the Mercure hub will generate one.
* `private` (boolean, optional, defaults to `false`) Flag this message as private (i.e. only authenticated subscribers may receive this message.)

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["id" => $uuid, "private" => true]
	)
);
```

## Mock

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\MockQueue` | Y       | Y       | - |
| `Nimbly\Syndicate\Adapter\MockSubscriber` | Y       | Y       | - |

A set of mock adapters are provided for your unit testing convenience. The `MockQueue` adapter implements `ConsumerInterface` and the `MockSubscriber` implements `SubscriberInterface`. Both adapters allow publishing of messages.

These adapters also offer convenience methods to inspect the message queues and subscriptions as well as the ability to flush all messages or messages within a specific topic.

### Options

Most of the major methods that support an `options` array, support an `"exception" => true` value to simulate an exception being thrown.

## MQTT

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Mqtt` | Y       | Y       | `php-mqtt/client` |

### Install

```bash
composer require php-mqtt/client
```

### Message attributes

The following message attributes are supported when publishing messages:

* `qos` (integer, optional, defaults to `MqttClient::QOS_AT_MOST_ONCE`) One of `MqttClient::QOS_AT_MOST_ONCE`, `MqttClient::QOS_AT_LEAST_ONCE`, or `MqttClient::QOS_EXACTLY_ONCE`.
* `retain` (boolean, optional, defaults to `false`) Retain the message on the source.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["qos" => MqttClient::QOS_EXACTLY_ONCE, "retain" => true]
	)
);
```

## NullPublisher

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\NullPublisher` | Y       | N       | - |

Don't need or care about messages actually being published? Then this adapter is for you! All calls to `publish` sends your message into the void.

This adapter is a good fit when you are developing locally and don't want or need messages to be published to a queue or broker.

By default, publishing will return a random hexadecimal string. Optionally, you can provide a `receipt` callback into the constructor to generate any receipt value you want.

## Outbox

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Outbox` | Y       | N       | `ext-pdo` |

This adapter uses a database as the means to publish messages to a specified table. This is a common pattern in EDA called the "outbox pattern."

Required minimum table structure is:

```sql
CREATE TABLE {:your table name:} (
	id {:any data type you want:} primary key,
	topic text not null,
	payload text,
	headers text, -- json serialized headers from message
	attributes text, -- json serialized attributes from message
	created_at timestamp not null
);
```

See https://microservices.io/patterns/data/transactional-outbox.html for a detailed explanation of the outbox pattern.

### Install

```bash
sudo apt-get install php-pdo
```

## RabbitMQ

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\RabbitMQ` | Y       | Y       | `php-amqplib/php-amqplib` |

### Install

```bash
composer require php-amqplib/php-amqplib
```

### Message attributes

The following message attributes are supported when publishing messages:

* `exchange` (string, optional, defaults to empty string) Name of exchange to use.
* `mandatory` (boolean, defaults to `false`) Defaults to false.
* `immediate` (boolean, defaults to `false`) Defaults to false.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["exchange" => "my-exchange", "mandatory" => true, "immediate" => true]
	)
);
```

## Redis Queue

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Redis` | Y       | Y       | `predis/predis` |

This adapter uses Redis's LIST feature to simulate a queue. Messages are `rpush`ed onto the list and `lpop`ed off when consuming.

### Install

```bash
composer require predis/predis
```

## Redis PubSub

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\RedisPubSub` | Y       | Y       | `predis/predis` |

This adapter uses Redis's built-in pubsub feature.

### Install

```bash
composer require predis/predis
```

## SNS

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Sns` | Y       | N       | `aws/aws-sdk-php` |

### Install

```bash
composer require aws/aws-sdk-php
```

### Message attributes

The following message attributes are supported when publishing messages:

* `MessageGroupId` (string, optional) The message group ID.
* `MessageDeduplicationId` (string, optional) The message deduplication ID.
* **any** All other values are assumed to be SNS message attributes. See https://docs.aws.amazon.com/sns/latest/dg/sns-message-attributes.html for more information.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["MessageGroupId" => $group, "MessageDeduplicationId" => $uuid]
	)
);
```

## SQS

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Sqs` | Y       | Y       | `aws/aws-sdk-php` |

### Install

```bash
composer require aws/aws-sdk-php
```

### Message attributes

The following message attributes are supported when publishing messages:

* `MessageGroupId` (string, optional) The message group ID.
* `MessageDeduplicationId` (string, optional) The message deduplication ID.
* **any**  All other values will be sent as `MessageAttributes` and must adhere to SQS guidelines for message metadata. @see See https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-message-metadata.html for more information.

## Webhook

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Webhook` | Y       | N       | `psr/http-client` |

This publisher will make HTTP calls to the given hostname and endpoint. It assumes the endpoint will be the topic name and will make a POST call.

If no `psr/http-client` implementation is given, this adapter will fall back to using `nimbly/shuttle` which is bundled with Syndicate.

### Install

This adapter only requires a `psr-http-client` implementation. If none is given, it will default to `nimbly/shuttle` which comes pre-bundled with Syndicate.

```php
$publisher = new Webhook(
	hostname: "https://api.example.com/events/",
	headers: [
		"Authorization" => "Bearer " . $api_token,
		"Content-Type" => "application/json",
	]
);
```

### Message headers

You may supply custom headers for the message. These headers will be merged with the default headers supplied in the constructor. The resulting merged headers will be included in the HTTP call.

```php
$publisher->publish(
	topic: "users",
	payload: \json_encode($user),
	headers: ["X-Verification-Id" => $verification_id]
);
```

### Publishing options

The following publishing options are supported:

* `method` (string|HttpMethod, optional) Override the default HTTP method used to send the request.

```php
$publisher->publish(
	topic: "users",
	payload: \json_encode($user),
	options: ["method" => "put"]
);
```

You may also provide a full URI for the Message topic. This will override any default hostname you defined (if any) in the constructor.

```php
$publisher = new Webhook(
	hostname: "https://api.example.com/events/",
	headers: [
		"Authorization" => "Bearer " . $api_token,
		"Content-Type" => "application/json",
	]
);

$publisher->publish(
	topic: "https://events.domain.com/users",
	payload: \json_encode($user)
);
```