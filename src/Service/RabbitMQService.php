<?php //-->
/**
 * This file is part of the Cradle PHP Library.
 * (c) 2016-2018 Openovate Labs
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

namespace Cradle\Framework\Queue\Service;

use Exception;
use Throwable;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AbstractConnection as Resource;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Wire\AMQPTable;

use Cradle\Framework\Queue\Service;

/**
 * RabbitMQ Service
 *
 * @vendor   Cradle
 * @package  Framework
 * @author   Christian Blanquera <cblanquera@openovate.com>
 * @standard PSR-2
 */
class RabbitMQService
{
    /**
     * @var array $data
     */
    protected $data = [];

    /**
     * @var string $delay
     */
    protected $delay = 0;

    /**
     * @var string $priority
     */
    protected $priority = 0;

    /**
     * @var string $queue
     */
    protected $queue = 'queue';

    /**
     * @var Resource $resource
     */
    protected $resource = 0;

    /**
     * @var string $retry
     */
    protected $retry = 0;

    /**
     * Set Resource
     *
     * @param *RabbitMQResource $resource
     */
    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Consumes Message
     *
     * @return bool
     */
    public function consume($callback)
    {
        try {
            $resource = $this->resource;
            $channel = $resource->channel();
        } catch(Throwable $e) {
            return false;
        } catch(Exception $e) {
            return false;
        }

        $name = $this->queue;

        // worker consuming tasks from queue
        $channel->basic_qos(null, 1, null);

        $consumer = function($message) use ($resource, $channel, $callback) {
            $info = json_decode($message->body, true);
            $serial = base64_encode(json_encode([
                'queue' => $info['queue'],
                'task' => $info['task'],
                'delay' => $info['delay'],
                'data' => $info['data']
            ]));

            //remove from redis
            Service::get('redis')->remove($serial);

            try {
                $results = call_user_func($callback, $info);
            } catch (Throwable $e) {
                $results = false;
            } catch (Exception $e) {
                $results = false;
            }

            //if it failed and theres a retry
            if(!$results
                && isset(
                    $info['queue'],
                    $info['task'],
                    $info['retry'],
                    $info['priority'],
                    $info['delay'],
                    $info['data']
                )
                && $info['retry']
            ) {
                //try to requeue
                (new self($resource))
                    ->setData($info['data'])
                    ->setDelay($info['delay'])
                    ->setPriority($info['priority'])
                    ->setQueue($info['queue'])
                    ->setRetry(--$info['retry'])
                    ->send($info['task']);
            }

            //remove from queue
            $channel = $message->delivery_info['channel'];
            $channel->basic_nack($message->delivery_info['delivery_tag']);
        };

        // now we need to catch the channel exception
        // when task does not exists in our queue
        try {
            // comsume messages on queue
            $channel->basic_consume(
                $name,
                '',
                false,
                false,
                false,
                false,
                $consumer->bindTo($this, get_class($this))
            );
        } catch (AMQPProtocolChannelException $e) {
            return false;
        } catch(Throwable $e) {
            return false;
        } catch(Exception $e) {
            return false;
        }

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        return true;
    }

    /**
     * Sends Message
     *
     * @param *string $task
     * @param bool    $duplicates
     *
     * @return bool
     */
    public function send($task, $duplicates = true)
    {
        try {
            $channel = $this->resource->channel();
        } catch(Throwable $e) {
            return false;
        } catch(Exception $e) {
            return false;
        }

        $name = $this->queue;
        $exchange = $name . '-xchnge';
        $delayName = $name . '-delay-' . ($this->delay / 1000);
        $delayExchange = $name . '-xchnge-delay-' . ($this->delay / 1000);

        $info = [
            'queue' => $name,
            'task' => $task,
            'retry' => $this->retry,
            'priority' => $this->priority,
            'delay' => $this->delay,
            'data' => $this->data
        ];

        //consider redis to store keys to check for duplicates
        $redis = Service::get('redis');
        $serial = base64_encode(json_encode([
            'queue' => $info['queue'],
            'task' => $info['task'],
            'delay' => $info['delay'],
            'data' => $info['data']
        ]));

        if(!$duplicates && $redis->exists($serial)) {
            return true;
        }

        $redis->add($serial, json_encode($info));

        $options = ['delivery_mode' => 2];

        if($this->priority) {
            $options['priority'] = $this->priority;
        }

        //declare the queue
        $channel->queue_declare(
            $name,
            false,
            true,
            false,
            false,
            false,
            ['x-max-priority' => ['I', 100]]
        );

        $channel->exchange_declare($exchange, 'direct');
        $channel->queue_bind($name, $exchange);
        $message = new AMQPMessage(json_encode($info), $options);

        // if no delay queue it now
        if (!$this->delay) {
            $channel->basic_publish($message, $exchange);
            return true;
        }

        $channel->queue_declare(
                $delayName,
                false,
                false,
                false,
                false,
                false,
                [
                    'x-max-priority' => ['I', 100],
                    'x-message-ttl' => ['I', $this->delay],
                    // after message expiration in delay queue, move message to the right.now.queue
                    'x-dead-letter-exchange' => ['S', $exchange]
                ]
        );

        $channel->exchange_declare(
            $delayExchange,
            'x-delayed-message',
            false,
            false,
            false,
            false,
            false,
            new AMQPTable([
               'x-delayed-type' => 'fanout'
            ])
        );

        $channel->queue_bind($delayName, $delayExchange);
        $channel->basic_publish($message, $delayExchange);

        return true;
    }

    /**
     * Set Data
     *
     * @param *array $data
     *
     * @return RabbitMQService
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set Delay
     *
     * @param *int $delay
     *
     * @return RabbitMQService
     */
    public function setDelay($delay)
    {
        //max is around 2147480
        $this->delay = min(2147480, $delay) * 1000;
        return $this;
    }

    /**
     * Set Priority
     *
     * @param *int $priority
     *
     * @return RabbitMQService
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Set Queue
     *
     * @param *string $queue
     *
     * @return RabbitMQService
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set Retries
     *
     * @param *int $retry
     *
     * @return RabbitMQService
     */
    public function setRetry($retry)
    {
        $this->retry = $retry;
        return $this;
    }
}
