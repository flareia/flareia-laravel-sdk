<?php

namespace FlareIA\Sdk\Laravel\Support;

class Configuration
{
    public static function envBoolean(string $key, bool $default): bool
    {
        return self::toBoolean(env($key), $default);
    }

    public static function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    /**
     * Bref / Serverless Lift often sets queue.connections.sqs.queue to the full SQS URL.
     * A literal "default" in FlareIA config must map to that URL, not prefix/default.
     */
    public static function resolveSenderQueue(?string $queue, ?string $connection = null): ?string
    {
        if ($queue === null || $queue === '') {
            return null;
        }

        if ($queue !== 'default') {
            return $queue;
        }

        $connection = $connection ?: (string) config('queue.default');
        $configured = config("queue.connections.{$connection}.queue");

        if (is_string($configured) && filter_var($configured, FILTER_VALIDATE_URL)) {
            return $configured;
        }

        return $queue;
    }
}
