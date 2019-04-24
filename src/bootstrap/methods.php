<?php //-->
/**
 * This file is part of a package designed for the CradlePHP Project.
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

use Cradle\Framework\Queue\Service\RabbitMQService;
use Cradle\Framework\Queue\Service\NoopService;

use Cradle\Http\Request\RequestInterface;
use Cradle\Http\Response\ResponseInterface;

use Composer\Spdx\SpdxLicenses;

if (!defined('WORKER_ID')) {
    define('WORKER_ID', md5(uniqid()));
}

return function(RequestInterface $request, ResponseInterface $response) {
    $this->package('cradlephp/cradle-queue')

    /**
     * Queuer
     *
     * @param string $task The task name
     * @param array  $data Data to use for this task
     */
    ->addMethod('queue', function($task = null, array $data = [], $queue = null) {
        $global = cradle('global');
        $resource = $global->service('rabbitmq-main');
        $settings = $global->config('settings');

        if (is_null($queue)) {
            $queue = 'queue';

            if(isset($settings['queue'])) {
                $queue = $settings['queue'];
            }
        }

        if($resource) {
            $service = new RabbitMQService($resource);
            $service
                ->setQueue($queue)
                ->setData($data);

            if(is_string($task)) {
                return $service->send($task);
            }

            return $service;
        } else if(!is_string($task)) {
            return new NoopService();
        }

        return false;
    })

    /**
     * Worker using traditional means
     */
    ->addMethod('work', function($queue = 'queue', $label = '') {
        $cradle = cradle();
        $resource = $cradle->package('global')->service('rabbitmq-main');

        if(!$resource) {
            return false;
        }

        $label .= '[' . substr(WORKER_ID, -6) . ']';

        // notify its up
        $cradle->log($label . ' Starting up.');

        $service = new RabbitMQService($resource);

        //run the consumer
        $service->setQueue($queue)->consume(function($info) use ($label, $cradle) {
            //check for body format
            if(!isset($info['task'])) {
                $cradle->log($label . ' Invalid task format. Flushing.');
                return true;
            }

            $label .= '[' . $info['task'] . ']';

            // notify once a task is received
            $cradle->log($label . ' is received. (' . $info['priority'] . ')');

            if(!isset($info['data'])) {
                $info['data'] = [];
            }

            if(!empty($info['data'])) {
                $cradle->log($label . ' Input:');
                $cradle->log(json_encode($info['data']));
            }

            $payload = $cradle->makePayload();
            $payload['request']->setStage($info['data']);

            try {
                $cradle->triggerEvent(
                    $info['task'],
                    $payload['request'],
                    $payload['response']
                );

                $cradle->log($label . ' Event finished.');
            } catch (Exception $e) {
                $cradle->log($label . ' Logic Error: ' . $e->getMessage() . '. Aborting');
                return false;
            } catch (Throwable $e) {
                $cradle->log($label . ' Logic Error: ' . $e->getMessage() . '. Aborting');
                return false;
            }

            if ($payload['response']->isError()) {
                $cradle->log($label . ' Response Error: ' . $payload['response']->getMessage() . '. Aborting');
                return false;
            }

            if($payload['response']->hasResults()) {
                $cradle->log($label . ' Output:');
                $cradle->log(json_encode($payload['response']->getResults()));
            }

            $cradle->log($label . ' has completed.');
            $cradle->log('');
            return true;
        });
    })

    /**
     * Worker using pcntl
     *
     * Beacuse we dont want PDO connections to stay open
     *
     * Getting: PHP Fatal error:  Uncaught AMQPRuntimeException:
     * Broken pipe or closed connection
     *
     * I think this is because the connection gets lost after the child is done.
     * Overall it's starts becoming problematic when we have sockets open between
     * parent and child.
     *
     * PHP's official answer to this is "It's not a problem when you open AMQP on a child"
     *
     * AMQP's answer is $resource->set_close_on_destruct(false);
     * which is what is rendering this error, but it does close
     * the child processes.
     */
    ->addMethod('workFork', function($queue = 'queue', $label = '') {
        $cradle = cradle();
        $resource = $cradle->package('global')->service('rabbitmq-main');

        if(!$resource) {
            return false;
        }

        //see: https://github.com/php-amqplib/php-amqplib/issues/202
        $resource->set_close_on_destruct(false);

        $label .= '[' . substr(WORKER_ID, -6) . ']';

        // notify its up
        $cradle->log($label . ' Starting up.');

        $service = new RabbitMQService($resource);

        //run the consumer
        $service->setQueue($queue)->consume(function($info) use ($label, $cradle) {
            //check for body format
            if(!isset($info['task'])) {
                $cradle->log($label . ' Invalid task format. Flushing.');
                return true;
            }

            $label .= '[' . $info['task'] . ']';

            // notify once a task is received
            $cradle->log($label . ' is received. (' . $info['priority'] . ')');

            if(!isset($info['data'])) {
                $info['data'] = [];
            }

            if(!empty($info['data'])) {
                $cradle->log($label . ' Input:');
                $cradle->log(json_encode($info['data']));
            }

            //here we fork
            $pid = pcntl_fork();
            if ($pid == -1) {
                $cradle->log($label . ' Could not spawn child. Aborting.');
                return false;
            }

            if (!$pid) {
                $label .= '[' . getmypid() . ']';
                // we are the child
                $cradle->log($label . ' Spawned child for process.');

                $request = (new Request())->load()->setStage($info['data']);
                $response = (new Response())->load();

                try {
                    $cradle->triggerEvent($info['task'], $request, $response);
                    $cradle->log($label . ' Child finished.');
                } catch (Exception $e) {
                    $cradle->log($label . ' Logic Error: ' . $e->getMessage() . '. Aborting');
                    exit(1);
                } catch (Throwable $e) {
                    $cradle->log($label . ' Logic Error: ' . $e->getMessage() . '. Aborting');
                    exit(1);
                }

                if ($response->isError()) {
                    $cradle->log($label . ' Response Error: ' . $response->getMessage() . '. Aborting');
                    exit(1);
                }

                if($response->hasResults()) {
                    $cradle->log($label . ' Output:');
                    $cradle->log(json_encode($response->getResults()));
                }

                exit(0);
            }

            // we are the parent
            //Protect against Zombie children
            pcntl_wait($status);

            // notify once a task is received
            $cradle->log($label . ' Child has finished.');

            if(pcntl_wifexited($status)) {
                $code = pcntl_wexitstatus($status);

                if($code == 1) {
                    $cradle->log($label . ' Child reported an error. Flushing.');
                    return true;
                }
            } else if(pcntl_wifsignaled($status)) {
                $cradle->log($label . ' Term Signal Error: ' . pcntl_wtermsig($status) . '. Aborting');
                return false;
            } else if(pcntl_wifstopped($status)) {
                $cradle->log($label . ' Stop Signal Error: ' . pcntl_wstopsig($status) . '. Aborting');
                return false;
            }

            $cradle->log($label . ' has completed.');
            $cradle->log('');
            return true;
        });
    })

    /**
     * Worker using exec/system
     */
    ->addMethod('workExec', function($queue = 'queue', $label = '', $verbose = false) {
        $cradle = cradle();
        $resource = $cradle->package('global')->service('rabbitmq-main');

        if(!$resource) {
            return false;
        }

        $label .= '[' . substr(WORKER_ID, -6) . ']';

        // notify its up
        $cradle->log($label . ' Starting up.');

        $service = new RabbitMQService($resource);

        //run the consumer
        $service->setQueue($queue)->consume(function($info) use ($label, $verbose, $cradle) {
            //check for body format
            if(!isset($info['task'])) {
                $cradle->log($label . ' Invalid task format. Flushing.');
                return true;
            }

            $label .= '[' . $info['task'] . ']';

            // notify once a task is received
            $cradle->log($label . ' is received. (' . $info['priority'] . ')');

            if(!isset($info['data'])) {
                $info['data'] = [];
            }

            if(!empty($info['data'])) {
                $cradle->log($label . ' Input:');
                $cradle->log(json_encode($info['data']));
            }

            //dont trust PWD
            $cwd = realpath(__DIR__ . '/../../../../..');

            //HAX using the composer package to get the root of the vendor folder
            //is there a better recommended way?
            if (class_exists(SpdxLicenses::class)
                && method_exists(SpdxLicenses::class, 'getResourcesDir')
                && realpath(SpdxLicenses::getResourcesDir() . '/../../..')
            ) {
                $cwd = realpath(SpdxLicenses::getResourcesDir() . '/../../..');
            }

            $command = sprintf(
                'cd %s && %sbin/cradle %s%s --__worker_id=%s --__json64=\'%s\'',
                $cwd,
                PHP_OS === 'Linux'? 'timeout 900 ':'',
                $info['task'],
                $verbose ? ' -v': '',
                WORKER_ID,
                base64_encode(json_encode($info['data']))
            );

            $cradle->log($label . ' Converting to the following command:');
            $cradle->log($command);

            try {
                system($command, $result);
                $cradle->log($label . ' Exec finished.');
            } catch (Exception $e) {
                $cradle->log($label . ' Exec Error: ' . $e->getMessage() . '. Aborting');
                return false;
            } catch (Throwable $e) {
                $cradle->log($label . ' Exec Error: ' . $e->getMessage() . '. Aborting');
                return false;
            }

            if(!$result) {
                $this->log($label . ' Exec no result output. Aborting');
                return false;
            }

            if(!empty($results)) {
                $cradle->log($label . ' Output:');
                $cradle->log($result);
            }

            $cradle->log($label . ' has completed.');
            $cradle->log('');
            return true;
        });
    });
};
