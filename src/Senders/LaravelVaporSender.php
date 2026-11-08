<?php

namespace FlareIA\Sdk\Laravel\Senders;

use FlareIA\Sdk\Laravel\Jobs\SendFlareIAPayload;
use FlareIA\Sdk\Laravel\Support\Configuration;
use FlareIA\Sdk\Senders\Sender;

/**
 * Queues FlareIA ingest payloads so shipping survives Lambda freeze-after-response.
 * Mirrors Spatie\LaravelFlare\Senders\LaravelVaporSender:
 * errors stay sync by default; traces/logs go to the queue.
 */
class LaravelVaporSender implements Sender
{
    protected string $senderClass;

    /** @var array<string, mixed> */
    protected array $senderConfig;

    protected bool $queueErrors;

    protected bool $queueTraces;

    protected bool $queueLogs;

    protected ?string $queue;

    protected ?string $connection;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
    ) {
        $this->senderClass = $this->config['sender'] ?? LaravelHttpSender::class;
        $this->senderConfig = $this->config['sender_config'] ?? [
            'timeout' => (int) ($this->config['timeout'] ?? config('flareia.timeout', 2)),
        ];
        $this->queueErrors = Configuration::toBoolean($this->config['queue_errors'] ?? null, false);
        $this->queueTraces = Configuration::toBoolean($this->config['queue_traces'] ?? null, true);
        $this->queueLogs = Configuration::toBoolean($this->config['queue_logs'] ?? null, true);
        $this->queue = isset($this->config['queue']) ? (string) $this->config['queue'] : null;
        $this->connection = isset($this->config['connection']) ? (string) $this->config['connection'] : null;
    }

    public function post(string $endpoint, string $apiKey, array $payload): void
    {
        $shouldQueue = $this->shouldQueue($endpoint);

        if (! $shouldQueue) {
            $this->resolveInnerSender()->post($endpoint, $apiKey, $payload);

            return;
        }

        $job = new SendFlareIAPayload(
            $this->senderClass,
            $this->senderConfig,
            $endpoint,
            $apiKey,
            $payload,
        );

        if ($this->connection !== null && $this->connection !== '') {
            $job->onConnection($this->connection);
        }

        if ($this->queue !== null && $this->queue !== '') {
            $job->onQueue($this->queue);
        }

        dispatch($job);
    }

    protected function shouldQueue(string $endpoint): bool
    {
        // Artisan/tests need a real HTTP round-trip, not a queued acknowledgement.
        if ($this->isConsole()) {
            return false;
        }

        return match ($this->entityType($endpoint)) {
            'errors' => $this->queueErrors,
            'traces' => $this->queueTraces,
            'logs' => $this->queueLogs,
            default => true,
        };
    }

    protected function isConsole(): bool
    {
        return app()->runningInConsole();
    }

    protected function entityType(string $endpoint): string
    {
        return match (true) {
            str_contains($endpoint, '/errors') => 'errors',
            str_contains($endpoint, '/traces') => 'traces',
            str_contains($endpoint, '/logs') => 'logs',
            default => 'unknown',
        };
    }

    protected function resolveInnerSender(): Sender
    {
        if ($this->senderClass === LaravelHttpSender::class) {
            return new LaravelHttpSender((int) ($this->senderConfig['timeout'] ?? 2));
        }

        return new ($this->senderClass)($this->senderConfig);
    }
}
