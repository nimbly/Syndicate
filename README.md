# Syndicate

See the syndicate-framework repo for framework documentation.

## Using without a Router and Dispatcher

### Create Queue instance
```php
$queue = new Syndicate\Queue\Sqs(
    getenv("SQS_QUEUE_URL"),
    new SqsClient([
        'version' => 'latest',
        'region' => 'us-west-2'
    ])
);
```

### Set a serializer and deserializer callback (optional)

The serializer callback is applied to each outgoing queue message payload. It defaults to ```\json_encode```.

You can pass in any ```\callable``` instance as the serializer.

```php
$queue->setSerializer(function($message){

    return \json_encode($message);

});
```

The deserializer callback is applied to each incoming queue message payload. It defaults to ```\json_decode```.

You can pass in any ```\callable``` instance as the deserializer. This is useful for performing more advanced
message deserialization.

For example, to handle deserializing a message payload from SQS that was forwarded by SNS, you could pass in
the following.

```php
$queue->setDeserializer(function($payload){

    $payload = \json_decode($payload);

    if( property_exists($payload, "Type") &&
        $payload->Type === "Notification" ){
        return \json_decode($payload->Message);
    }

    return $payload;

});
```

### Listen on queue

Listening is a blocking call and your callback will be triggered when a new Message has arrived.

```php
$queue->listen(function(Message $message){

    echo "Message received!\n";
    $message->delete();

});
```

## Using a Router and Dispatcher

Using a Router and Dispatcher you can have your messages passed off to specific Handlers. How you route is up to you and the message format.

Commonly, a message will contain a message type or event name - these are prime candidates for keys to routing.

### Router
Create a new ```Router``` by passing in the ```\callable``` route resolver and an ```array``` of key and value pairs as the route definitions.

```php
$router = new Router(function(Message $message, string $route){

    return $message->getPayload()->eventName == $route;

}, [

    'UserRegistered' => ["\App\Handlers\UserHandler", "userRegistered"],
    'UserClosedAccount' => ["\App\Handlers\UserHandler", "userAccountClosed"]

]);
```

### Dispatcher
Create a new ```Dispatcher``` by passing the ```Router``` instance.

```php
$dispatcher = new Dispatcher($router);
```

### Add a default handler
If the ```Router``` cannot resolve a route for the ```Message```, the ```Dispatcher``` will attempt to pass the message off to the default handler.

```php
$dispatcher->setDefaultHandler(function(Message $message){

    Log::critical("No route defined for {$message->getPayload()->eventName}!");
    $message->release();

});
```

### Starting the listener
```php
$dispatcher->listen($queue);
```

## Putting it all together
```php
// Create Queue instance
$queue = new Syndicate\Queue\Sqs(
    getenv("SQS_QUEUE_URL"),
    new SqsClient([
        'version' => 'latest',
        'region' => 'us-west-2'
    ])
);

// Set a custom deserializer.
$queue->setDeserializer(function($payload){

    $payload = \json_decode($payload);

    if( property_exists($payload, "Type") &&
        $payload->Type === "Notification" ){
        return \json_decode($payload->Message);
    }

    return $payload;

});

// Create Router instance with a resolver and our list of routes.
$router = new Router(function(Message $message, string $route){

    return $message->getPayload()->eventName == $route;

}, [

    'UserRegistered' => ["\App\Handlers\UserHandler", "userRegistered"],
    'UserClosedAccount' => ["\App\Handlers\UserHandler", "userAccountClosed"]

]);

// Create Dispatcher instance.
$dispatcher = new Dispatcher($router);

// Set a default handler.
$dispatcher->setDefaultHandler(function(Message $message){

    Log::critical("No route defined for {$message->getPayload()->eventName}!");
    $message->release();

});

// Listen for new messages.
$dispatcher->listen();
```