<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RabbitMQApiQueueService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $rabbitmqHost,
        private string $rabbitmqPort,
        private string $rabbitmqUser,
        private string $rabbitmqPassword,
        private string $rabbitmqVhost = '/',
    ) {
    }

    public function getQueueStatsViaApi(string $queueName = 'download_queue'): array
    {
        try {
            $url = \sprintf(
                'http://%s:%s/api/queues/%s/%s',
                $this->rabbitmqHost,
                $this->rabbitmqPort,
                \urlencode($this->rabbitmqVhost),
                $queueName
            );

            $response = $this->httpClient->request('GET', $url, [
                'auth_basic' => [$this->rabbitmqUser, $this->rabbitmqPassword],
            ]);

            $data = $response->toArray();

            return [
                'messages_ready'          => $data['messages_ready'] ?? 0,
                'messages_unacknowledged' => $data['messages_unacknowledged'] ?? 0,
                'messages_total'          => $data['messages'] ?? 0,
                'consumers'               => $data['consumers'] ?? 0,
                'state'                   => $data['state'] ?? 'unknown',
                'memory'                  => $data['memory'] ?? 0,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('RabbitMQ API error: ' . $e->getMessage());
        }
    }

    /**
     * Получить количество сообщений в процессе обработки.
     */
    public function getProcessingMessagesCount(string $queueName = 'download_queue'): int
    {
        try {
            $stats = $this->getQueueStatsViaApi($queueName);

            return $stats['messages_unacknowledged'] ?? 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getWaitingMessagesCount(string $queueName = 'download_queue'): int
    {
        try {
            $stats = $this->getQueueStatsViaApi($queueName);

            return $stats['messages_ready'] ?? 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getAllQueuesDetailedStats(): array
    {
        $queues = ['download_queue', 'failed_queue'];
        $stats  = [];

        foreach ($queues as $queue) {
            try {
                $stats[$queue] = $this->getQueueStatsViaApi($queue);
            } catch (\Throwable $e) {
                $stats[$queue] = [
                    'messages_ready'          => 0,
                    'messages_unacknowledged' => 0,
                    'messages_total'          => 0,
                    'consumers'               => 0,
                    'state'                   => 'error',
                    'error'                   => $e->getMessage(),
                ];
            }
        }

        $stats['total'] = [
            'waiting'          => \array_sum(\array_column($stats, 'messages_ready')),
            'processing'       => \array_sum(\array_column($stats, 'messages_unacknowledged')), // ТЕПЕРЬ ПРАВИЛЬНО!
            'total_messages'   => \array_sum(\array_column($stats, 'messages_total')),
            'active_consumers' => \array_sum(\array_column($stats, 'consumers')),
        ];

        return $stats;
    }

    public function isRabbitMQAvailable(): bool
    {
        try {
            $url = \sprintf('http://%s:%s/api/overview', $this->rabbitmqHost, $this->rabbitmqPort);

            $response = $this->httpClient->request('GET', $url, [
                'auth_basic' => [$this->rabbitmqUser, $this->rabbitmqPassword],
                'timeout'    => 5,
            ]);

            return 200 === $response->getStatusCode();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
