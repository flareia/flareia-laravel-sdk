<?php

namespace FlareIA\Sdk\Laravel\Recorders;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Fills {@see ErrorTimeline} the way Flare fills the error debug timeline:
 * the command or job that started the work, then queries and logs in order.
 */
class TimelineRecorder
{
    private ?string $currentCommand = null;

    public function __construct(
        private ErrorTimeline $timeline,
    ) {}

    public function register(): void
    {
        Event::listen(RequestHandled::class, function (): void {
            $this->timeline->reset();
        });

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $this->currentCommand = $event->command;
            $this->timeline->reset();
            $this->rememberCommand($event->command);
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $command = $this->currentCommand;
            $this->timeline->reset();

            if (is_string($command) && $command !== '') {
                $this->rememberCommand($command);
            }

            $this->timeline->push([
                'type' => 'job',
                'name' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'time' => 0,
            ]);
        });

        DB::listen(function (QueryExecuted $query): void {
            $this->timeline->query(
                $query->sql,
                $query->bindings,
                (float) $query->time,
                $query->connectionName,
            );
        });

        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $level = $event->level instanceof \BackedEnum ? (string) $event->level->value : (string) $event->level;
            $message = $event->message instanceof \Stringable ? (string) $event->message : (string) $event->message;
            $this->timeline->log($level, $message);
        });
    }

    private function rememberCommand(string $command): void
    {
        $this->timeline->push([
            'type' => 'command',
            'name' => $command,
            'time' => 0,
        ]);
    }
}
