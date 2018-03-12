# cradle-queue

RabbitMQ with Fork and Exec workers. Built for the [Cradle Framework](https://cradlephp.github.io/) 

## Install

```
composer require cradlephp/cradle-queue
```

Then in `/bootstrap.php`, add

```
->register('cradlephp/cradle-queue')
```

## Setup

Open `/config/services.php` and add

```
'rabbitmq-main' => [
    'host' => '127.0.0.1',
    'port' => 5672,
    'user' => 'guest',
    'pass' => 'guest'
],
```

## Methods

```
cradle('global')->queue(*string $event, array $data);
```

An easy way to queue.

```
cradle('global')
    ->queue()
    ->setData(*array $data)
    ->setDelay(*string $delay)
    ->setPriority(*int $priority)
    ->setQueue(*string $queueName)
    ->setRetry(*int $retry)
    ->send(*string $task, bool $duplicates = true);
```

Returns the queue class for advance manipulation. If you want to prevent
duplicates from entering your queue, set the `$duplicates` flag to false and
turn on Redis (this is the only way I can figure this can happen).

## CommandLine

You can queue events via command line like the following example.

```bash
$ cradle queue event-name foo=bar zoo=foo
```

To start a worker use any of the following commands.

```bash
$ cradle work
$ cradle work --mode exec
$ cradle work --mode fork
```

 - `cradle work --mode exec` - Uses `exec()` to work on tasks. This is used incase you want to manage your background process
 - `cradle work --mode fork` - Uses `pntl_fork` to work on tasks. This is another way to manage your background process
