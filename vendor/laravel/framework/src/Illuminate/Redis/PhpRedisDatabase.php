<?php

namespace Illuminate\Redis;

use Redis;
use Closure;
use RedisCluster;
use Illuminate\Support\Arr;

class PhpRedisDatabase extends Database
{
    /**
     * The host address of the database.
     *
     * @var array
     */
    protected $clients;

    /**
     * Create a new Redis connection instance.
     *
     * @param  array  $servers
     * @return void
     */
    public function __construct(array $servers = [])
    {
        $cluster = Arr::pull($servers, 'cluster');

        $options = (array) Arr::pull($servers, 'options');

        if ($cluster) {
            $this->clients = $this->createAggregateClient($servers, $options);
        } else {
            $this->clients = $this->createSingleClients($servers, $options);
        }
    }

    /**
     * Create a new aggregate client supporting sharding.
     *
     * @param  array  $servers
     * @param  array  $options
     * @return array
     */
    protected function createAggregateClient(array $servers, array $options = [])
    {
        $servers = array_map([$this, 'buildClusterSeed'], $servers);

        return ['default' => new RedisCluster(
            null,
            array_values($servers),
            Arr::get($options, 'timeout', 0),
            Arr::get($options, 'read_timeout', 0),
            isset($options['persistent']) && $options['persistent']
        )];
    }

    /**
     * Create an array of single connection clients.
     *
     * @param  array  $servers
     * @param  array  $options
     * @return array
     */
    protected function createSingleClients(array $servers, array $options = [])
    {
        $clients = [];

        foreach ($servers as $key => $server) {
            $client = new Redis;

            $timeout = empty($server['timeout']) ? 0 : $server['timeout'];

            if (isset($server['persistent']) && $server['persistent']) {
                $client->pconnect($server['host'], $server['port'], $timeout);
            } else {
                $client->connect($server['host'], $server['port'], $timeout);
            }

            if (! empty($server['prefix'])) {
                $client->setOption(Redis::OPT_PREFIX, $server['prefix']);
            }

            if (! empty($server['read_timeout'])) {
                $client->setOption(Redis::OPT_READ_TIMEOUT, $server['read_timeout']);
            }

            if (! empty($server['password'])) {
                $client->auth($server['password']);
            }

            if (! empty($server['database'])) {
                $client->select($server['database']);
            }

            $clients[$key] = $client;
        }

        return $clients;
    }

    /**
     * Build a single cluster seed string from array.
     *
     * @param  array  $server
     * @return string
     */
    protected function buildClusterSeed(array $server)
    {
        $parameters = Arr::only($server, [
            'database', 'password', 'prefix', 'read_timeout',
        ]);

        return $server['host'].':'.$server['port'].'?'.http_build_query($parameters);
    }

    /**
     * Subscribe to a set of given channels for messages.
     *
     * @param  array|string  $channels
     * @param  \Closure  $callback
     * @param  string  $connection
     * @param  string  $method
     * @return void
     */
    public function subscribe($channels, Closure $callback, $connection = null, $method = 'subscribe')
    {
        call_user_func_array([$this->connection($connection), $method], (array) $channels, $callback);
    }

    /**
     * Subscribe to a set of given channels with wildcards.
     *
     * @param  array|string  $channels
     * @param  \Closure  $callback
     * @param  string  $connection
     * @return void
     */
    public function psubscribe($channels, Closure $callback, $connection = null)
    {
        $this->subscribe($channels, $callback, $connection, __FUNCTION__);
    }
}
