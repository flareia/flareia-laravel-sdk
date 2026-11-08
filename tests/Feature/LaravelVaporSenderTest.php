<?php

use FlareIA\Sdk\Laravel\Tests\TestCase;

uses(TestCase::class);

use FlareIA\Sdk\Laravel\FlareIAServiceProvider;
use FlareIA\Sdk\Laravel\Jobs\SendFlareIAPayload;
use FlareIA\Sdk\Laravel\Senders\LaravelBrefSender;
use FlareIA\Sdk\Laravel\Senders\LaravelHttpSender;
use FlareIA\Sdk\Laravel\Senders\LaravelVaporSender;
use FlareIA\Sdk\Senders\Sender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('does not queue errors when queue_errors is the string false', function () {
    Queue::fake();
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    $sender = new class(['queue_errors' => 'false', 'timeout' => 2]) extends LaravelVaporSender
    {
        protected function isConsole(): bool
        {
            return false;
        }
    };

    $sender->post('https://flare.test/api/v1/errors', 'key', ['exception' => ['message' => 'boom']]);

    Queue::assertNothingPushed();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/errors'));
});

it('queues traces and logs but sends errors synchronously by default', function () {
    Queue::fake();
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    $sender = new class(['timeout' => 2, 'queue' => 'flareia', 'connection' => 'sqs']) extends LaravelVaporSender
    {
        protected function isConsole(): bool
        {
            return false;
        }
    };

    $sender->post('https://flare.test/api/v1/errors', 'key', ['exception' => ['message' => 'boom']]);
    $sender->post('https://flare.test/api/v1/traces', 'key', ['resourceSpans' => []]);
    $sender->post('https://flare.test/api/v1/logs', 'key', ['resourceLogs' => []]);

    Queue::assertPushed(SendFlareIAPayload::class, 2);
    Queue::assertPushed(SendFlareIAPayload::class, function (SendFlareIAPayload $job) {
        return str_contains($job->endpoint, '/traces')
            && $job->queue === 'flareia'
            && $job->connection === 'sqs';
    });
    Queue::assertPushed(SendFlareIAPayload::class, function (SendFlareIAPayload $job) {
        return str_contains($job->endpoint, '/logs');
    });

    Http::assertSent(fn ($request) => str_contains($request->url(), '/errors'));
});

it('never queues while running in the console so flareia:test stays synchronous', function () {
    Queue::fake();
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    expect(app()->runningInConsole())->toBeTrue();

    $sender = new LaravelVaporSender([
        'queue_errors' => true,
        'queue_traces' => true,
        'queue_logs' => true,
    ]);

    $sender->post('https://flare.test/api/v1/traces', 'key', ['resourceSpans' => []]);

    Queue::assertNothingPushed();
    Http::assertSentCount(1);
});

it('SendFlareIAPayload posts through LaravelHttpSender', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    $job = new SendFlareIAPayload(
        LaravelHttpSender::class,
        ['timeout' => 2],
        'https://flare.test/api/v1/errors',
        'secret-key',
        ['exception' => ['message' => 'from-job']],
    );

    $job->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://flare.test/api/v1/errors'
            && $request->hasHeader('x-api-token', 'secret-key')
            && $request['exception']['message'] === 'from-job';
    });
});

it('LaravelBrefSender queues traces and logs but sends errors synchronously by default', function () {
    Queue::fake();
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    $sender = new class(['timeout' => 2, 'queue' => 'flareia', 'connection' => 'sqs']) extends LaravelBrefSender
    {
        protected function isConsole(): bool
        {
            return false;
        }
    };

    $sender->post('https://flare.test/api/v1/errors', 'key', ['exception' => ['message' => 'boom']]);
    $sender->post('https://flare.test/api/v1/traces', 'key', ['resourceSpans' => []]);
    $sender->post('https://flare.test/api/v1/logs', 'key', ['resourceLogs' => []]);

    Queue::assertPushed(SendFlareIAPayload::class, 2);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/errors'));
});

it('LaravelBrefSender is a LaravelVaporSender for instanceof checks', function () {
    expect(new LaravelBrefSender)->toBeInstanceOf(LaravelVaporSender::class);
});

it('resolves LaravelBrefSender from the container when configured', function () {
    config([
        'flareia.sender.class' => LaravelBrefSender::class,
        'flareia.sender.config' => ['timeout' => 2],
    ]);

    (new FlareIAServiceProvider(app()))->register();

    expect(app(Sender::class))->toBeInstanceOf(LaravelBrefSender::class);
});
