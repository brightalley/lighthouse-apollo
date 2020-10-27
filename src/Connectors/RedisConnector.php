<?php

namespace BrightAlley\LighthouseApollo\Connectors;

use BrightAlley\LighthouseApollo\TracingResult;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;

class RedisConnector
{
    private const REDIS_KEY = 'lighthouse_apollo_tracings';

    /**
     * @var RedisConnection
     */
    private $redis;

    /**
     * Constructor.
     *
     * @param Repository $config
     * @param RedisFactory $redis
     */
    public function __construct(Repository $config, RedisFactory $redis)
    {
        $this->redis = $redis->connection($config->get('lighthouse-apollo.redis_connection'));
    }

    /**
     * Add a new tracing result to the redis queue.
     *
     * @param TracingResult $tracingResult
     */
    public function put(TracingResult $tracingResult): void
    {
        $this->putMany([$tracingResult]);
    }

    /**
     * Add multiple tracing results to the redis queue.
     *
     * @param TracingResult[] $tracings
     */
    public function putMany(array $tracings): void
    {
        $this->redis->command('rpush', [self::REDIS_KEY, ...array_map(static function (TracingResult $tracingResult) {
            return serialize($tracingResult);
        }, $tracings)]);
    }

    /**
     * @return TracingResult[]
     */
    public function getPending(): array
    {
        // Check the number of results in the redis key, so we can take them all.
        $length = $this->redis->command('llen', [self::REDIS_KEY]);
        $result = [];
        while (
            count($result) < $length &&
            $serializedTracingResult = $this->redis->command('lpop', [self::REDIS_KEY])
        ) {
            $result[] = unserialize($serializedTracingResult, [
                'allowed_classes' => [TracingResult::class],
            ]);
        }

        return $result;
    }
}
