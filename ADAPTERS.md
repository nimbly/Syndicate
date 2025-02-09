# Adapters

The following is a list of currently supported adapters and any particular notes, special options, and message attributes that can be used with them.

## PubSub

### Gearman

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\PubSub\Gearman` | Y       | Y       | `ext-gearman` |


#### Requirements

Use of this adapter requires the PHP `ext-gearman` module.

**NOTE:** Only background jobs are supported.


```bash
sudo apt-get install php-gearman
```

Or you can install directly from PEAR/PECL.

```bash
sudo pecl install gearman
```

#### Message attributes

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

### Google

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\PubSub\Google` | Y       | Y       | `google/cloud-pubsub` |

#### Requirements

To use this adapter, you must install the `google/cloud-pubsub` library.

```bash
composer require google/cloud-pubsub
```

### Mercure

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\PubSub\Mercure` | Y       | N       | n/a |

#### Requirements

This adapter only requires a `psr-http-client` implementation. If none is given, it will default to `nimbly/shuttle` which comes pre-bundled with Syndicate.

Mercure hub requires a JWT to publish messages. Please refer to the Mercure documentation on JWT format and required claims.

#### Message attributes

The following message attributes are supported when publishing a message:

* `id` (string, optional) A unique ID for this message. If none provided, the Mercure hub will generate one.
* `private` (boolean, optional, defaults to `false`) Flag this message as private (i.e. only authenticated subscribers may receive this message.)
* `type` (string, optional) The message type.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["id" => $uuid, "private" => true, "type" => ""]
	)
);
```

### MQTT

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\PubSub\Mqtt` | Y       | Y       | `php-mqtt/client` |

#### Requirements

This adapter requires the `php-mqtt/client` library.

```bash
composer require php-mqtt/client
```

#### Message attributes

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

### Redis

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\PubSub\Redis` | Y       | Y       | `predis/predis` |

#### Requirements

This adapter requires the `predis/predis` library.

```bash
composer require predis/predis
```

### SNS

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\PubSub\Sns` | Y       | N       | `aws/aws-sdk-php` |

#### Requirements

This adapter requires the `aws/aws-sdk-php` library.

```bash
composer require aws/aws-sdk-php
```

#### Message attributes

The following message attributes are supported when publishing messages:

* `MessageGroupId` (string, optional) The message group ID.
* `MessageDeduplicationId` (string, optional) The message deduplication ID.
* **any** See https://docs.aws.amazon.com/sns/latest/dg/sns-message-attributes.html for more information.

```php
$publisher->publish(
	new Message(
		topic: "fruits",
		payload: "bananas",
		attributes: ["MessageGroupId" => $group, "MessageDeduplicationId" => $uuid]
	)
);
```

### Webhook

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\PubSub\Webhook` | Y       | N       | n/a |

#### Requirements

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

#### Message headers

You may supply custom headers for the message. These headers will be merged with the default headers supplied in the constructor. The resulting merged headers will be included in the HTTP call.

```php
$publisher->publish(
	topic: "users",
	payload: \json_encode($user),
	headers: ["X-Verification-Id" => $verification_id]
);
```

#### Publishing options

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

## Queue


### Azure

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Queue\Azure` | Y       | Y       | `microsoft/azure-storage-queue` |

#### Requirements

This adapter requires the `microsoft/azure-storage-queue` library.

```bash
composer require microsoft/azure-storage-queue
```

### Beanstalk

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Queue\Beanstalk` | Y       | Y       | `pda/pheanstalk` |

#### Requirements

This adapter requires the `pda/pheanstalk` library.

```bash
composer require pda/pheanstalk
```

#### Message attributes

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

### IronMQ

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Queue\Iron` | Y       | Y       | `iron-io/iron_mq` |

#### Requirements

This adapter requires the `iron-io/iron_mq` library.

```bash
composer require iron-io/iron_mq
```

#### Message attributes

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

### Outbox

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Queue\Outbox` | Y       | N       | `ext-pdo` |

#### Requirements

This adapter requires the PHP `ext-pdo` module.

```bash
sudo apt-get install php-pdo
```

### RabbitMQ

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Queue\RabbitMQ` | Y       | Y       | `php-amqplib/php-amqplib` |

#### Requirements

This adapter requires the `php-amqplib/php-amqplib` library.

```bash
composer require php-amqplib/php-amqplib
```

#### Message attributes

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

### Redis

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Queue\Redis` | Y       | Y       | `predis/predis` |

#### Requirements

This adapter requires the `predis/predis` library.

```bash
composer require predis/predis
```

### SQS

| Adapter | Publish | Consume | Library |
|---------|---------|---------|---------|
| `Nimbly\Syndicate\Adapter\Queue\Sqs` | Y       | Y       | `aws/aws-sdk-php` |

#### Requirements

This adapter requires the `aws/aws-sdk-php` library.

```bash
composer require aws/aws-sdk-php
```