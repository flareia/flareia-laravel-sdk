<?php

namespace FlareIA\Sdk\Laravel\Recorders;

use FlareIA\Sdk\Spans\Span;
use FlareIA\Sdk\Tracer;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

/**
 * Auto-instruments queue job execution into spans — the Laravel-adapter
 * equivalent of Flare's JobRecorder.
 */
class JobRecorder
{
    /** @var array<string, int> job id => start time in unix nanoseconds */
    private array $startedAt = [];

    public function __construct(
        private Tracer $tracer,
    ) {}

    public function register(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->startedAt[$event->job->getJobId()] = (int) (microtime(true) * 1_000_000_000);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            $this->recordAndFlush($event->job, $event->connectionName, failed: false);
        });

        // JobProcessed only fires when the job completes without throwing —
        // a failing job never reaches it, so without this listener a span
        // would never be recorded (and flushed) for it at all.
        Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            $this->recordAndFlush($event->job, $event->connectionName, failed: true);
        });
    }

    private function recordAndFlush(object $job, string $connectionName, bool $failed): void
    {
        $jobId = $job->getJobId();
        $startNano = $this->startedAt[$jobId] ?? null;
        unset($this->startedAt[$jobId]);

        $endNano = (int) (microtime(true) * 1_000_000_000);

        $this->tracer->record(new Span(
            traceId: $this->tracer->currentTraceId(),
            spanId: Tracer::newSpanId(),
            parentSpanId: null,
            name: $job->resolveName(),
            type: 'job',
            startTimeUnixNano: $startNano ?? $endNano,
            endTimeUnixNano: $endNano,
            attributes: [
                'messaging.destination.name' => $job->getQueue(),
                'messaging.system' => $connectionName,
                'error' => $failed,
            ],
        ));

        $this->tracer->flush();
    }
}
