<?php //-->
/**
 * This file is part of the Cradle PHP Library.
 * (c) 2016-2018 Openovate Labs
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

namespace Cradle\Framework\Queue;

use Cradle\Framework\Queue\Service\RedisService;
use Cradle\Framework\Queue\Service\RabbitMQService;
use Cradle\Framework\Queue\Service\NoopService;

/**
 * Service layer
 *
 * @vendor   Cradle
 * @package  Framework
 * @author   Christian Blanquera <cblanquera@openovate.com>
 * @standard PSR-2
 */
class Service
{
    /**
     * Returns a service. To prevent having to define a method per
     * service, instead we roll everything into one function
     *
     * @param *string $name
     * @param string  $key
     *
     * @return object
     */
    public static function get($name, $key = 'main')
    {
        if (in_array($name, ['rabbitmq', 'redis'])) {
            $resource = cradle()->package('global')->service($name . '-' . $key);

            if ($resource) {
                if ($name === 'redis') {
                    return new RedisService($resource);
                }

                if ($name === 'rabbitmq') {
                    return new RabbitMQService($resource);
                }
            }
        }

        if ($name === 'rabbitmq') {
            return new NoopService();
        }

        return new NoopService('redis');
    }
}
