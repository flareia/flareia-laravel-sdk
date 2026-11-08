<?php

namespace FlareIA\Sdk\Laravel\Console;

use FlareIA\Sdk\Laravel\FlareIA;
use Illuminate\Console\Command;
use RuntimeException;

class SendTestEventCommand extends Command
{
    protected $signature = 'flareia:test';

    protected $description = 'Send a synthetic exception through the FlareIA SDK.';

    public function handle(FlareIA $flareIA): int
    {
        $this->info('Sending a test exception to '.config('flareia.base_url').'...');

        try {
            throw new RuntimeException('This is a test exception from the FlareIA SDK.');
        } catch (RuntimeException $e) {
            $flareIA->report($e, ['triggered_via' => 'flareia:test command'], handled: true);
        }

        $this->info('Sent. With LaravelVaporSender, flareia:test posts synchronously; with async_via_queue + HTTP sender, run a queue worker.');

        return self::SUCCESS;
    }
}
