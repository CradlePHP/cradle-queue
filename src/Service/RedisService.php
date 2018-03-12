<?php //-->
/**
 * This file is part of the Cradle PHP Library.
 * (c) 2016-2018 Openovate Labs
 *
 * Copyright and license information can be found at LICENSE.txt
 * distributed with this package.
 */

namespace Cradle\Framework\Queue\Service;

use Predis\Client as Resource;
use Predis\Connection\ConnectionException;

/**
 * Redis Service
 *
 * @vendor   Cradle
 * @package  Framework
 * @author   Christian Blanquera <cblanquera@openovate.com>
 * @standard PSR-2
 */
class RedisService
{
    /**
     * @const string CACHE_KEY
     */
    const CACHE_KEY = 'rabbitmq-tasks';

    /**
     * @var Resource|null $resource
     */
    protected $resource = null;

    /**
     * Registers the resource for use
     *
     * @param Resource $resource
     */
    public function __construct(Resource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Cache an id
     *
     * @param *string $id
     *
     * @return bool
     */
    public function add($id, $data = 1)
    {
        try {
            return $this->resource->hSet(static::CACHE_KEY, $id, $data);
        } catch (ConnectionException $e) {
            return false;
        }
    }

    /**
     * Deletes everything in this key
     *
     * @return bool
     */
    public function flush()
    {
        try {
            $this->resource->del(static::CACHE_KEY);
        } catch (ConnectionException $e) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if a key exists
     *
     * @param *string $id
     *
     * @return bool
     */
    public function exists($id)
    {
        try {
            return !!$this->resource->hExists(static::CACHE_KEY, $id);
        } catch (ConnectionException $e) {
            return false;
        }
    }

    /**
     * Remove a cache key
     *
     * @param *string $id
     *
     * @return array
     */
    public function remove($id)
    {
        try {
            return $this->resource->hDel(static::CACHE_KEY, $id);
        } catch (ConnectionException $e) {
            return false;
        }
    }
}
