<?php //-->

use Cradle\Http\Request;
use Cradle\Http\Response;

use Cradle\Framework\CommandLine;
use Cradle\Framework\Queue\Service\RabbitMQService;

/**
 * CLI queue - bin/cradle queue auth-verify auth_slug=<email>
 *
 * @param Request $request
 * @param Response $response
 *
 * @return string
 */
$cradle->on('queue', function(Request $request, Response $response) {
    $data = $request->getStage();

    if (!isset($data[0])) {
        CommandLine::error('Not enough arguments. Usage: bin/cradle cradlephp/cradle-queue event data');
    }

    $resource = cradle('global')->service('rabbitmq-main');

    if (!$resource) {
        CommandLine::error('Unable to queue, check config/services.php for correct connection information.');
    }

    $event = array_shift($data);

    $priority = 0;
    if (isset($data['priority'])) {
        $priority = $data['priority'];
        unset($data['priority']);
    }

    $delay = 0;
    if (isset($data['delay'])) {
        $delay = $data['delay'];
        unset($data['delay']);
    }

    $retry = 0;
    if (isset($data['retry'])) {
        $delay = $data['retry'];
        unset($data['retry']);
    }

    $resource = cradle('global')->service('rabbitmq-main');
    $settings = cradle('global')->config('settings');

    $queue = 'queue';
    if(isset($settings['queue'])) {
        $queue = $settings['queue'];
    }

    (new RabbitMQService($resource))
        ->setQueue($queue)
        ->setData($data)
        ->setDelay($delay)
        ->setPriority($priority)
        ->setRetry($retry)
        ->send($event);

    CommandLine::info('Queued: `' . $event . '` into `' . $queue . '`');
});


/**
 * CLI worker - bin/cradle work
 *
 * @param Request $request
 * @param Response $response
 *
 * @return string
 */
$cradle->on('work', function(Request $request, Response $response) {
    //get the queue name
    $name = 'queue';
    if ($request->hasStage(0)) {
        $name = $request->getStage(0);
    } else if ($request->hasStage('name')) {
        $name = $request->getStage('name');
    }

    $verbose = false;
    if($request->hasStage('v') || $request->hasStage('verbose')) {
        $verbose = true;
        cradle()->addLogger(function($message) {
            echo $message . PHP_EOL;
        });
    }

    $mode = 'work';
    if($request->hasStage('m')) {
        $mode = $request->getStage('m');
    } else if($request->hasStage('mode')) {
        $mode = $request->getStage('mode');
    }

    switch($mode) {
        case 'fork':
            $mode = 'workFork';
            break;
        case 'exec':
            $mode = 'workExec';
            break;
    }

    cradle('cradlephp/cradle-queue')->$mode($name, '[cradle]', $verbose);
});
