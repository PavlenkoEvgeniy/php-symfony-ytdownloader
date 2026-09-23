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

    /**
     * @return array{
     *     messages_ready: int,
     *     messages_unacknowledged: int,
     *     messages_total: int,
     *     consumers: int,
     *     state: string,
     *     memory: int,
     * }
     */
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

    public function getProcessingMessagesCount(string $queueName = 'download_queue'): int
    {
        try {
            $stats = $this->getQueueStatsViaApi($queueName);

            return $stats['messages_unacknowledged'];
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
