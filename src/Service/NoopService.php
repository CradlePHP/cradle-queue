<?php //-->
/**
 * This file is part of the Cradle PHP Library.
 * (c) 2016-2018 Openovate Labs
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

namespace Cradle\Framework\Queue\Service;

/**
 * Comment Noop Service
 *
 * @vendor   Salaaap
 * @package  Utility
 * @author   Christian Blanquera <cblanquera@openovate.com>
 * @standard PSR-2
 */
class NoopService
{
    /**
     * @var *string $type
     */
    private $type = null;

    /**
     * Always return false
     *
     * @param string $type
     *
     * @return false
     */
    public function __construct($type = null)
    {
        $this->type = $type;
    }

    /**
     * Always return false
     *
     * @param *string $name
     * @param *array  $args
     *
     * @return false
     */
    public function __call($name, array $args)
    {
        if($this->type === 'redis') {
            return false;
        }

        return $this;
    }

    /**
     * Consumes Message
     *
     * @return bool
     */
    public function consume()
    {
        return false;
    }

    /**
     * Sends Message
     *
     * @return bool
     */
    public function send()
    {
        return false;
    }
}
