<?php

namespace FlareIA\Sdk\Laravel\Jobs;

use FlareIA\Sdk\Api;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendErrorReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    public function handle(Api $api): void
    {
        $api->sendError($this->payload);
    }
}
