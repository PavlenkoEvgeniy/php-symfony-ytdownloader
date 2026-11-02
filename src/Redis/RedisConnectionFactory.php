<?php

declare(strict_types=1);

namespace App\Redis;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RedisConnectionFactory
{
    public function __construct(
        #[Autowire(env: 'string:REDIS')]
        private string $redisUrl,
    ) {
    }

    /**
     * @psalm-suppress UndefinedClass RedisAdapter's union mentions optional Relay classes.
     */
    public function create(): \Redis
    {
        $connection = RedisAdapter::createConnection($this->redisUrl);

        if (!$connection instanceof \Redis) {
            throw new \RuntimeException('Redis connection is not a Redis instance.');
        }

        return $connection;
    }
}
