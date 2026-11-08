<?php

namespace FlareIA\Sdk\Laravel\Senders;

use FlareIA\Sdk\Senders\Sender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laravel-idiomatic transport (uses the Http facade, so it's testable with
 * Http::fake() and participates in Laravel's HTTP client config), conforming
 * to the core's Sender contract — the same role Spatie\LaravelFlare's
 * LaravelHttpSender plays over Spatie\FlareClient\Senders\Sender.
 */
class LaravelHttpSender implements Sender
{
    public function __construct(
        private int $timeout = 2,
    ) {}

    public function post(string $endpoint, string $apiKey, array $payload): void
    {
        try {
            $response = Http::withHeader('x-api-token', $apiKey)
                ->timeout($this->timeout)
                ->post($endpoint, $payload);

            if ($response->failed()) {
                Log::channel(config('logging.default'))
                    ->debug('FlareIA: failed to report to '.$this->logHost($endpoint), ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            Log::channel(config('logging.default'))
                ->debug('FlareIA: failed to report to '.$this->logHost($endpoint), ['exception' => $e->getMessage()]);
        }
    }

    private function logHost(string $endpoint): string
    {
        $host = parse_url($endpoint, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'FlareIA';
    }
}
