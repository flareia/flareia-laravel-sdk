<?php

namespace FlareIA\Sdk\Laravel\Jobs;

use FlareIA\Sdk\Laravel\Senders\LaravelHttpSender;
use FlareIA\Sdk\Senders\Sender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delivers a FlareIA ingest payload from a queue worker — same role as
 * Spatie\LaravelFlare\Jobs\SendFlarePayload for Vapor-friendly shipping.
 */
class SendFlareIAPayload implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  class-string<Sender>  $senderClass
     * @param  array<string, mixed>  $senderConfig
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $senderClass,
        public array $senderConfig,
        public string $endpoint,
        public string $apiKey,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $sender = $this->resolveSender();
        $sender->post($this->endpoint, $this->apiKey, $this->payload);
    }

    protected function resolveSender(): Sender
    {
        if ($this->senderClass === LaravelHttpSender::class) {
            return new LaravelHttpSender((int) ($this->senderConfig['timeout'] ?? 2));
        }

        return new ($this->senderClass)($this->senderConfig);
    }
}
