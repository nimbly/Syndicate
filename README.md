# Syndicate

## Install

```bash
composer require nimbly/syndicate
```

## Basic usage

### Create Queue instance

```php
$queue = new Syndicate\Queue\Sqs(
    "https://queue.url",
    new SqsClient([
        'version' => 'latest',
        'region' => 'us-west-2'
    ])
);
```

### Listen on queue

Listening is a **blocking** call and runs in an infinite loop. Your callback will be triggered when a new Message has arrived.

```php
$queue->listen(function(Message $message): void {

	/**
	 *
	 *  Process the message...
	 *
	 */

	// Delete the message from Queue.
	$message->delete();

});
```

### Setting a custom serializer and deserializer

By default, Syndicate assumes your messages are JSON and will attempt to auto serialize/deserialize accordingly.

However, if your messages are in some other format, you may supply your own serializer and deserializer callbacks.

The serializer is applied to all outgoing message payloads.

```php
$queue->setSerializer(function($message): string {

    return \json_encode($message);

});
```

The deserializer callback is applied to all incoming message payloads.

For example, to handle deserializing a message payload from SQS that was forwarded by SNS, you could pass in the following deserializer callback.

```php
$queue->setDeserializer(function($payload) {

    $payload = \json_decode($payload);

    if( \property_exists($payload, "Type") &&
        $payload->Type === "Notification" ){
        return \json_decode($payload->Message);
    }

    return $payload;

});
```

### Shutting down the Queue

You may shutdown the queue by using the `shutdown()` method.

The Queue instance will respond to PCNTL signals in a safe manner that will not interrupt in the middle of Message processing.
You can install signal handlers in your code to cleanly and safely shutdown the service.

```php
\pcntl_signal(
	SIGINT,
	function() use ($queue): void {

		Log::info("[SIGNAL] Shutting down queue.");
		$queue->shutdown();

	}
);
```

## Routing and Dispatching

Using the `Dispatcher` and `Router` you can have your messages passed off to specific Handlers. How you route is up to you and the message format.

Commonly, a message will contain a message type or event name - these are prime candidates for keys to routing.

### Router

Create a new `Router` instance by passing in a `\callable` route resolver and an `array` of key and value pairs as route definitions.

### Route resolver

The route resolver is responsible for taking the incoming Message instance and finding a matching route to dispatch the Message to.

The dispatcher will loop through all configured routes and call the resolver with the Message and a route.

The resolver must simple return a `bool` value indicating whether the message matches the given route.


### Route definitions

The route definitions are an array of key/value pairs mapping any key you want to either a `callable`, `string` in the format of `Full\Namespace\ClassName@methodName`, or an array of the above.


```php
$router = new Router(function(Message $message, string $routeKey): bool {

    return $message->getPayload()->eventName == $routeKey;

}, [

	'UserLoggedOff' => function(Message $message): void {
		// Do some session cleanup stuff...
	},

	'UserRegistered' => '\App\Handlers\UserHandler@userRegistered',

    'UserClosedAccount' => [
		'\App\Handlers\UserHandler@userAccountClosed',
		'\App\Handlers\NotificationHandler@userAccountClosed'
	]

]);
```

### Dispatcher

Create a new `Dispatcher` instance by passing the `Router` instance.

```php
$dispatcher = new Dispatcher($router);
```

### PSR-11 Container support

The `Dispatcher` can accept a PSR-11 compliant `ContainerInterface` instance to be used during dependency resolution when dispatching a matched message to a handler.

```php
$dispatcher = new Dispatcher(
	$router,
	$container
);
```

Or you can call the `setContainer` method directly.

```php
$dispatcher->setContainer($container);
```

The `Dispatcher` will attempt to resolve any dependencies your handler requires including the `Syndicate\Message` instance.

### Add a default handler

If the `Router` cannot resolve a route for the `Message`, the `Dispatcher` will attempt to pass the message off to its default handler.

The default handler can be set as a `callable` and accepts the `Message` instance.

```php
$dispatcher->setDefaultHandler(function(Message $message): void {

    Log::critical("No route defined for {$message->getPayload()->eventName}!");
    $message->release();

});
```

If the Message cannot be dispatched and no default handler was given, a `DispatchException` will be thrown.

### Using the Dispatcher with the Queue

```php
$queue->listen(function(Message $message) use ($dispatcher): void {

	$dispatcher->dispatch($message);

});
```