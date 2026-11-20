<?php

namespace FlareIA\Sdk\Laravel\Recorders;

use DateTimeInterface;

/**
 * Events collected during the current request, job or command and attached
 * to the next error report. Same idea as Flare: queries, logs, the entry
 * point and the exception, in the order they happened.
 */
class ErrorTimeline
{
    private float $startedAt;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    public function reset(): void
    {
        $this->events = [];
        $this->startedAt = microtime(true);
    }

    public function offsetMs(): float
    {
        return round((microtime(true) - $this->startedAt) * 1000, 2);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function push(array $event): void
    {
        if (! isset($event['time'])) {
            $event['time'] = $this->offsetMs();
        }

        $this->events[] = $event;

        $max = 100;
        if (count($this->events) > $max) {
            $this->events = array_slice($this->events, -$max);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @param  list<mixed>  $bindings
     */
    public function query(string $sql, array $bindings, float $durationMs, ?string $connection): void
    {
        $statement = $this->interpolate($sql, $bindings);
        if (strlen($statement) > 2000) {
            $statement = substr($statement, 0, 2000).'…';
        }

        $this->push([
            'type' => 'query',
            'name' => $statement,
            'sql' => $statement,
            'duration' => round($durationMs, 2),
            'connection' => $connection,
            'time' => round(max(0, $this->offsetMs() - $durationMs), 2),
        ]);
    }

    public function log(string $level, string $message): void
    {
        $message = trim($message);
        if ($message === '') {
            return;
        }

        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000).'…';
        }

        $this->push([
            'type' => 'log',
            'level' => strtoupper($level),
            'name' => $level,
            'message' => $message,
        ]);
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private function interpolate(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $placeholder = strpos($sql, '?');
            if ($placeholder === false) {
                break;
            }

            $before = substr($sql, 0, $placeholder);
            $value = $this->isSensitive($before)
                ? "'[redacted]'"
                : $this->exportBinding($binding);
            $sql = $before.$value.substr($sql, $placeholder + 1);
        }

        return $sql;
    }

    private function isSensitive(string $before): bool
    {
        return preg_match('/\b(password|password_confirmation|token|secret|api_key|remember_token)\b[^\'"`]*$/i', $before) === 1;
    }

    private function exportBinding(mixed $binding): string
    {
        if ($binding === null) {
            return 'null';
        }

        if (is_bool($binding)) {
            return $binding ? '1' : '0';
        }

        if (is_int($binding) || is_float($binding)) {
            return (string) $binding;
        }

        if ($binding instanceof DateTimeInterface) {
            return "'".$binding->format('Y-m-d H:i:s')."'";
        }

        return "'".str_replace("'", "\\'", (string) $binding)."'";
    }
}
