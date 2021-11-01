<?php

namespace BrightAlley\LighthouseApollo\Connectors;

use BrightAlley\LighthouseApollo\TracingResult;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;

class RedisConnector
{
    private const REDIS_KEY = 'lighthouse_apollo_tracings';

    private RedisConnection $redis;

    private bool $redisSupportsLpopWithCount = false;

    /**
     * Constructor.
     *
     * @param Repository $config
     * @param RedisFactory $redis
     */
    public function __construct(Repository $config, RedisFactory $redis)
    {
        $this->redis = $redis->connection($config->get('lighthouse-apollo.redis_connection'));

        $redisInfo = $this->redis->command('info', ['server']);
        $this->redisSupportsLpopWithCount = version_compare(
            $redisInfo['Server']['redis_version'] ?? '1.0.0',
            '6.2.0',
            '>='
        ) === true;
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
     * @param array<int,TracingResult> $tracings
     */
    public function putMany(array $tracings): void
    {
        $this->redis->command('rpush', [self::REDIS_KEY, ...array_map(static function (TracingResult $tracingResult) {
            return serialize($tracingResult);
        }, $tracings)]);
    }

    /**
     * @return array<int,TracingResult>
     */
    public function getPending(int $limit = 100): array
    {
        // Check the number of results in the redis key, so we can take them all.
        $length = $this->redis->command('llen', [self::REDIS_KEY]);
        $resultsToFetch = min($length, $limit);

        return $this->fetchTracingsFromRedis($resultsToFetch);
    }

    /**
     * @return array<int,TracingResult>
     */
    protected function fetchTracingsFromRedis(int $count): array
    {
        if ($this->redisSupportsLpopWithCount) {
             $result = $this->redis->command('lpop', [self::REDIS_KEY, $count]);
        } else {
            $result = [];
            while (
                count($result) < $count &&
                $serializedTracingResult = $this->redis->command('lpop', [self::REDIS_KEY])
            ) {
                $result[] = $serializedTracingResult;
            }
        }

        return array_map(
            static fn(string $serializedTracingResult): TracingResult =>
                unserialize($serializedTracingResult, ['allowed_classes' => [TracingResult::class]]),
            $result
        );
    }
}
