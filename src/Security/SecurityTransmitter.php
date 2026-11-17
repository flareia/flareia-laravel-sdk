<?php

namespace FlareIA\Sdk\Laravel\Security;

use FlareIA\Sdk\Api;
use FlareIA\Sdk\Laravel\Jobs\SendFlareIAPayload;
use FlareIA\Sdk\Laravel\Senders\LaravelHttpSender;
use FlareIA\Sdk\Laravel\Senders\LaravelVaporSender;
use FlareIA\Sdk\Laravel\Support\Configuration;
use Illuminate\Support\Facades\Cache;

class SecurityTransmitter
{
    public function __construct(
        protected Api $api,
        protected SecurityPayloadBuffer $buffer,
    ) {}

    public function flush(bool $force = false): void
    {
        if ($this->buffer->isEmpty()) {
            return;
        }

        if (! config('flareia.enabled', true) || ! config('flareia.security.enabled', true)) {
            $this->buffer->drain();

            return;
        }

        $lockKey = 'flareia:security-flush:'.gethostname();

        if (! $force && ! Cache::lock($lockKey, 60)->get()) {
            if ($this->buffer->localAuthFailures() < (int) config('flareia.security.flush_on_auth_failures', 10)) {
                return;
            }
        }

        $payload = $this->buffer->drain();
        $this->buffer->resetLocalAuthFailures();

        if ($payload['events'] === [] && $payload['buckets'] === []) {
            return;
        }

        $this->send($payload);
    }

    /**
     * @param  array{events: list<array<string, mixed>>, buckets: list<array<string, mixed>>}  $payload
     */
    public function send(array $payload): void
    {
        $endpoint = rtrim((string) config('flareia.base_url'), '/').'/api/v1/security';
        $apiKey = (string) config('flareia.api_key');
        $sender = (string) config('flareia.sender.class', LaravelHttpSender::class);

        $queueSecurity = config('flareia.sender.config.queue_security', true);

        if ($this->shouldQueueSecurity($sender, $queueSecurity)) {
            $connection = config('flareia.sender.config.connection');
            $queue = Configuration::resolveSenderQueue(
                config('flareia.sender.config.queue', config('flareia.queue', 'default')),
                is_string($connection) ? $connection : null,
            );

            $pending = SendFlareIAPayload::dispatch(
                $sender === LaravelVaporSender::class ? LaravelHttpSender::class : $sender,
                array_merge(config('flareia.sender.config', []), ['timeout' => (int) config('flareia.timeout', 2)]),
                $endpoint,
                $apiKey,
                $payload,
            );

            if (is_string($connection) && $connection !== '') {
                $pending->onConnection($connection);
            }

            if ($queue !== null && $queue !== '') {
                $pending->onQueue($queue);
            }

            return;
        }

        $this->api->sendSecurity($payload);
    }

    protected function shouldQueueSecurity(string $sender, mixed $queueSecurity): bool
    {
        if (! filter_var($queueSecurity, FILTER_VALIDATE_BOOL) && $queueSecurity !== true) {
            return false;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        return $sender !== LaravelHttpSender::class || config('flareia.async_via_queue', true);
    }
}