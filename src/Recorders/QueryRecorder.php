<?php

namespace FlareIA\Sdk\Laravel\Recorders;

use FlareIA\Sdk\Spans\Span;
use FlareIA\Sdk\Tracer;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Auto-instruments database queries into spans, the Laravel-adapter
 * equivalent of Flare's QueryRecorder — only available here because plain
 * PHP has no DB::listen()-style framework hook to tap into.
 */
class QueryRecorder
{
    public function __construct(
        private Tracer $tracer,
    ) {}

    public function register(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $endNano = (int) (microtime(true) * 1_000_000_000);
            $startNano = $endNano - (int) ($query->time * 1_000_000);

            $this->tracer->record(new Span(
                traceId: $this->tracer->currentTraceId(),
                spanId: Tracer::newSpanId(),
                parentSpanId: null,
                name: $this->shortSql($query->sql),
                type: 'query',
                startTimeUnixNano: $startNano,
                endTimeUnixNano: $endNano,
                attributes: [
                    'db.statement' => $query->sql,
                    'db.system' => $query->connectionName,
                ],
            ));
        });
    }

    private function shortSql(string $sql): string
    {
        return strlen($sql) > 160 ? substr($sql, 0, 160).'…' : $sql;
    }
}
