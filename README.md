## Syndicate

### Installation

```bash
composer require nimbly/syndicate
```

## Jobs
Jobs are a self-contained unit of work that should be processed out-of-band from the current Request/Response cycle.

Prime examples of running background jobs are sending of phone verification SMS text messages, sending trigger-based emails, etc.

### Creating jobs
Your job should extend ```Syndicate\Job``` and implement the abstract ```run``` method.

The entire Job instance itself is serialized and placed in the queue.

```php
class SendSms extends Syndicate\Job
{
    protected $user;
    protected $message;

    public function __construct(User $user, $message)
    {
        $this->user = $user;
        $this->message = $message;
    }

    public function run(Syndicate\Queue $queue)
    {
        Sms::send($this->user->phone, $this->message);
        $queue->delete($this);
    }
}
```

### Queueing

Now place the entire job instance on the queue.

```php
$jobQueue->put(new SendSms($user, "Welcome to Syndicate!"));
```

### Processing jobs

```php
$jobQueue->listen(function(Syndicate\Message $message) use ($jobQueue) {

    $job->run($jobQueue, $message);

});
```
### Deleting jobs

```php
$this->delete();
```

### Releasing jobs

```php
$this->release();
````

