# cradle-queue

RabbitMQ with Fork and Exec workers. Built for the [Cradle Framework](https://cradlephp.github.io/)

## Install

If you already installed Cradle, you may not need to install this because it
should be already included.

```
composer require cradlephp/cradle-queue
bin/cradle cradlephp/cradle-queue install
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

 ----

 <a name="contributing"></a>
 # Contributing to Cradle PHP

 Thank you for considering to contribute to Cradle PHP.

 Please DO NOT create issues in this repository. The official issue tracker is located @ https://github.com/CradlePHP/cradle/issues . Any issues created here will *most likely* be ignored.

 Please be aware that master branch contains all edge releases of the current version. Please check the version you are working with and find the corresponding branch. For example `v1.1.1` can be in the `1.1` branch.

 Bug fixes will be reviewed as soon as possible. Minor features will also be considered, but give me time to review it and get back to you. Major features will **only** be considered on the `master` branch.

 1. Fork the Repository.
 2. Fire up your local terminal and switch to the version you would like to
 contribute to.
 3. Make your changes.
 4. Always make sure to sign-off (-s) on all commits made (git commit -s -m "Commit message")

 ## Making pull requests

 1. Please ensure to run [phpunit](https://phpunit.de/) and
 [phpcs](https://github.com/squizlabs/PHP_CodeSniffer) before making a pull request.
 2. Push your code to your remote forked version.
 3. Go back to your forked version on GitHub and submit a pull request.
 4. All pull requests will be passed to [Travis CI](https://travis-ci.org/CradlePHP/cradle-queue) to be tested. Also note that [Coveralls](https://coveralls.io/github/CradlePHP/cradle-queue) is also used to analyze the coverage of your contribution.
